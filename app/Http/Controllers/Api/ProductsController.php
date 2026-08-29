<?php

namespace App\Http\Controllers\Api;

use App\Repositories\ProductRepository;
use App\Services\BaseResponseService;
use App\Services\DataTableService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controller CRUD cho tài nguyên Product.
 *
 * Khác với mặc định của ApiCrudController, index() trả shape DATATABLE
 * (danh sách + thông tin phân trang) đúng cấu trúc mà bảng VDataTableServer
 * của Vuetify cần, nên frontend gắn thẳng vào bảng không phải chuyển đổi.
 */
class ProductsController extends ApiCrudController
{
    /**
     * Inject DataTableService để tự dựng shape datatable trong indexResponse().
     *
     * @param  BaseResponseService  $responder  Service trả response chuẩn hoá (của class cha).
     * @param  DataTableService  $dataTable  Service biến paginator thành shape VDataTableServer.
     */
    public function __construct(BaseResponseService $responder, protected DataTableService $dataTable)
    {
        parent::__construct($responder);
    }

    protected function repositoryClass(): string
    {
        return ProductRepository::class;
    }

    /**
     * Override hook của class cha: trả shape DATATABLE thay vì phân trang gốc.
     *
     * Gọi trực tiếp DataTableService::fromPaginator() để biến paginator thành
     * cấu trúc mà VDataTableServer của Vuetify đọc trực tiếp được, sau đó bọc
     * trong envelope chuẩn. `data` sẽ có dạng:
     * { products: [...], total, page, itemsPerPage, lastPage }.
     *
     * @param  Request  $request  Request gốc (criteria đã được áp trong index()).
     * @param  LengthAwarePaginator  $paginator  Kết quả phân trang sản phẩm.
     */
    protected function indexResponse(Request $request, LengthAwarePaginator $paginator): JsonResponse
    {
        return $this->responder->success(
            $this->dataTable->fromPaginator($paginator, 'products')
        );
    }

    protected function createRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'price' => ['required', 'numeric', 'min:0'],
            'product_group_id' => ['nullable', 'integer', 'exists:product_group,id'],
            'is_active' => ['nullable', 'boolean'],
            'image_url' => ['nullable', 'string', 'max:255'],
        ];
    }

    protected function updateRules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'price' => ['sometimes', 'required', 'numeric', 'min:0'],
            'product_group_id' => ['sometimes', 'nullable', 'integer', 'exists:product_group,id'],
            'is_active' => ['sometimes', 'nullable', 'boolean'],
            'image_url' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
