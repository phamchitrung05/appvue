<?php

namespace App\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;

/**
 * Chuẩn hoá cấu trúc JSON trả về cho frontend.
 *
 * Mọi response đều tuân theo một envelope duy nhất:
 *
 * {
 *     "success": true|false,   // kết quả xử lý
 *     "status": 200,           // HTTP status code
 *     "code": "OK",            // mã định danh ổn định (lỗi: PRODUCT_NOT_FOUND...)
 *     "message": "...",        // thông báo hiển thị được cho người dùng
 *     "data": ...              // payload (bỏ qua khi lỗi không có dữ liệu)
 * }
 *
 * Message mặc định được lấy từ `config/messages.php` — nơi tập trung
 * toàn bộ câu chữ API để dễ tra cứu và đổi ngôn ngữ. Trường `code` trên
 * response lỗi là mã KHÔNG ĐỔI (không phụ thuộc ngôn ngữ/message) để
 * frontend báo lại, backend tra tức thì ra lỗi gì. Tuỳ chọn mở rộng:
 * `errors` (lỗi validate).
 */
class BaseResponseService
{
    public function __construct(protected DataTableService $dataTable) {}

    /**
     * Response thành công.
     *
     * @param  mixed  $data  Payload trả về cho frontend, chấp nhận null.
     * @param  string|null  $message  Thông báo; để null lấy mặc định từ config.
     * @param  int  $status  HTTP status code (2xx).
     */
    public function success(mixed $data = null, ?string $message = null, int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'status' => $status,
            'code' => 'OK',
            'message' => $message ?? config('messages.common.success'),
            'data' => $data,
        ], $status);
    }

    /**
     * Response lỗi.
     *
     * @param  string|null  $message  Thông báo lỗi; để null lấy mặc định từ config.
     * @param  int  $status  HTTP status code (4xx/5xx).
     * @param  mixed  $errors  Chi tiết lỗi (thường là bag lỗi validate).
     * @param  string|null  $code  Mã lỗi ổn định; để null tự tra theo HTTP status
     *                             từ bảng `messages.codes.by_status` (vd: 404 => RESOURCE_NOT_FOUND).
     */
    public function error(?string $message = null, int $status = 400, mixed $errors = null, ?string $code = null): JsonResponse
    {
        $body = [
            'success' => false,
            'status' => $status,
            // Mã lỗi để frontend báo lại — backend đọc là biết ngay lỗi gì.
            'code' => $code ?? config("messages.codes.by_status.{$status}") ?? 'UNKNOWN_ERROR',
            'message' => $message ?? config('messages.common.error'),
        ];

        if ($errors !== null) {
            $body['errors'] = $errors;
        }

        return response()->json($body, $status);
    }

    /**
     * Response tạo mới tài nguyên thành công (201).
     *
     * @param  string|null  $resource  Tên tài nguyên (vd: 'Product') để nội suy message.
     */
    public function created(mixed $data = null, ?string $message = null, ?string $resource = null): JsonResponse
    {
        return $this->success($data, $message ?? $this->crudMessage('created', $resource), 201);
    }

    /**
     * Response cập nhật tài nguyên thành công.
     *
     * @param  string|null  $resource  Tên tài nguyên (vd: 'Product') để nội suy message.
     */
    public function updated(mixed $data = null, ?string $message = null, ?string $resource = null): JsonResponse
    {
        return $this->success($data, $message ?? $this->crudMessage('updated', $resource));
    }

    /**
     * Response lấy chi tiết một bản ghi thành công,
     * message dạng "Đã tải thông tin {tài nguyên}."
     *
     * @param  string|null  $resource  Tên tài nguyên (vd: 'Product') để nội suy message.
     */
    public function fetched(mixed $data = null, ?string $resource = null): JsonResponse
    {
        return $this->success($data, $this->crudMessage('fetched', $resource));
    }

    /**
     * Response xoá thành công, không trả body (204).
     */
    public function noContent(): JsonResponse
    {
        return response()->json(null, 204);
    }

    /**
     * Response không tìm thấy tài nguyên (404).
     *
     * Khi có `$resource`, code mang tên tài nguyên viết HOA
     * (vd: Product => PRODUCT_NOT_FOUND) giúp xác định ngay tài nguyên nào lỗi.
     *
     * @param  string|null  $message  Thông báo; để null lấy mặc định từ config.
     * @param  string|null  $resource  Tên tài nguyên (vd: 'Product') để nội suy message + code.
     */
    public function notFound(?string $message = null, ?string $resource = null): JsonResponse
    {
        $code = null;

        if ($resource !== null) {
            $code = str_replace(':RESOURCE', strtoupper($resource), config('messages.codes.crud.not_found'));
        }

        return $this->error($message ?? $this->crudMessage('not_found', $resource), 404, null, $code);
    }

    /**
     * Response danh sách phân trang cho VDataTableServer.
     *
     * `data` nhận shape do DataTableService dựng (items/total/page/...)
     * nên frontend gắn thẳng vào bảng Vuetify mà không cần chuyển đổi.
     *
     * @param  LengthAwarePaginator  $paginator  Kết quả paginate() từ repository.
     * @param  string  $itemsKey  Tên khoá danh sách, vd: `products`.
     * @param  (callable(mixed): mixed)|null  $transform  Bộ biến đổi từng dòng.
     */
    public function paginate(LengthAwarePaginator $paginator, string $itemsKey = 'items', ?callable $transform = null): JsonResponse
    {
        return $this->success(
            $this->dataTable->fromPaginator($paginator, $itemsKey, $transform),
            config('messages.common.success')
        );
    }

    /**
     * Lấy message CRUD từ config, thay `:resource` bằng nhãn tiếng Việt.
     *
     * Tên model (vd: 'Product') được tra bảng `config('messages.resources')`
     * để ra nhãn tiếng Việt (vd: 'sản phẩm') trước khi nội suy. Model chưa
     * khai trong bảng thì dùng nguyên tên gốc để vẫn tra cứu được.
     * Khi không truyền `$resource`, dùng message chung tương ứng của nhóm common.
     */
    protected function crudMessage(string $key, ?string $resource = null): string
    {
        if ($resource === null) {
            return config("messages.common.{$key}");
        }

        $label = config("messages.resources.{$resource}", $resource);

        return str_replace(':resource', $label, config("messages.crud.{$key}"));
    }
}
