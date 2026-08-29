<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\BaseResponseService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Prettus\Repository\Contracts\RepositoryInterface;

/**
 * Base controller cho các API JSON CRUD vận hành trên l5-repository.
 *
 * Các subclass chỉ cần khai báo repository interface và rules validate.
 *
 * Điểm mở rộng chính: index() tách phần logic chung (criteria, per_page,
 * paginate) khỏi phần DỰNG RESPONSE. Việc dựng response được ủy thác cho
 * hook indexResponse() — subclass override hook này khi muốn shape dữ liệu
 * riêng cho từng model (datatable Vuetify, phân trang, gom nhóm, ...).
 */
abstract class ApiCrudController extends Controller
{
    protected RepositoryInterface $repository;

    /**
     * Inject service trả response chuẩn hoá (envelope + message tiếng Việt
     * đọc từ config/messages.php) và khởi tạo repository từ class do subclass khai báo.
     */
    public function __construct(protected BaseResponseService $responder)
    {
        $this->repository = app($this->repositoryClass());
    }

    /**
     * Tên class đầy đủ của repository interface mà subclass quản lý.
     */
    abstract protected function repositoryClass(): string;

    /**
     * Rules validate cho store().
     */
    abstract protected function createRules(): array;

    /**
     * Rules validate cho update().
     */
    abstract protected function updateRules(): array;

    /**
     * Tên model do repository quản lý, dùng nội suy message tiếng Việt
     * qua bảng `messages.resources` trong config (vd: 'Product' => 'sản phẩm').
     */
    protected function resourceName(): string
    {
        return class_basename($this->repository->makeModel());
    }

    /**
     * Danh sách có phân trang: áp criteria (tìm kiếm/lọc/sắp xếp) rồi ủy thác
     * việc dựng response cho hook indexResponse().
     *
     * Repository đã tự push DataTableCriteria trong boot() — criteria này kế
     * thừa RequestCriteria và BỔ SUNG whitelist cho sort + lọc, nên không push
     * RequestCriteria gốc ở đây nữa (tránh áp 2 lần và tránh đường đi kiểu
     * `?orderBy=<cột lạ>` vượt qua whitelist gây lỗi SQL).
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', (string) config('repository.pagination.limit', 15));

        return $this->indexResponse($request, $this->repository->paginate($perPage));
    }

    /**
     * HOOK dựng response cho index() — override ở subclass khi cần shape riêng.
     *
     * Mặc định trả shape phân trang gốc của Laravel (current_page, data,
     * total, per_page...) — phù hợp danh sách cuộn vô hạn (vd: Order).
     *
     * @param  Request  $request  Request gốc (đọc thêm tham số nếu cần).
     * @param  LengthAwarePaginator  $paginator  Kết quả paginate() từ repository.
     */
    protected function indexResponse(Request $request, LengthAwarePaginator $paginator): JsonResponse
    {
        return $this->responder->success($paginator->toArray());
    }

    /**
     * Tạo bản ghi mới sau khi validate, trả message
     * "Đã tạo {tài nguyên} thành công." theo config.
     *
     * @param  Request  $request  Request chứa dữ liệu đã qua validate.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate($this->createRules());

        $item = $this->repository->create($data);

        return $this->responder->created($item, resource: $this->resourceName());
    }

    /**
     * Lấy chi tiết một bản ghi theo id, trả 404 nếu không tồn tại.
     */
    public function show(int $id): JsonResponse
    {
        try {
            $item = $this->repository->find($id);
        } catch (ModelNotFoundException) {
            return $this->notFound();
        }

        return $this->responder->fetched($item, $this->resourceName());
    }

    /**
     * Cập nhật bản ghi theo id sau khi validate, trả 404 nếu không tồn tại.
     *
     * @param  Request  $request  Request chứa dữ liệu đã qua validate.
     * @param  int  $id  Khóa chính của bản ghi cần cập nhật.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $data = $request->validate($this->updateRules());

        try {
            $item = $this->repository->update($data, $id);
        } catch (ModelNotFoundException) {
            return $this->notFound();
        }

        return $this->responder->updated($item, resource: $this->resourceName());
    }

    /**
     * Xoá bản ghi theo id, trả 204 không kèm body; 404 nếu không tồn tại.
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $this->repository->delete($id);
        } catch (ModelNotFoundException) {
            return $this->notFound();
        }

        return $this->responder->noContent();
    }

    /**
     * Response 404 dùng chung, message nội suy từ tên model
     * (vd: "Không tìm thấy sản phẩm.").
     */
    protected function notFound(): JsonResponse
    {
        return $this->responder->notFound(resource: $this->resourceName());
    }
}
