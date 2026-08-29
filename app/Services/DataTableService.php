<?php

namespace App\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Dựng các shape dữ liệu (cấu trúc JSON) mà frontend Vuetify tiêu thụ.
 *
 * Service này nhận kết quả phân trang từ repository và biến thành cấu trúc
 * mà các component bảng của Vuetify (VDataTableServer) đọc trực tiếp được,
 * giúp controller và frontend không phải tự chuyển đổi dữ liệu.
 */
class DataTableService
{
    /**
     * Chuẩn hoá paginator thành shape mà VDataTableServer mong đợi:
     * danh sách có tên khoá dùng cho `:items` và tổng số bản ghi TRƯỚC khi
     * phân trang dùng cho `:items-length` (để Vuetify tính đúng số trang).
     *
     * @param  LengthAwarePaginator  $paginator  Kết quả paginate() từ repository.
     * @param  string  $itemsKey  Tên khoá của danh sách trong response, vd: `products`.
     * @param  (callable(mixed): mixed)|null  $transform  Hàm tuỳ chọn biến đổi từng dòng
     *                                                    (vd: thêm trường tính toán, format giá) trước khi trả về frontend.
     * @return array<string, mixed> Mảng gồm: $itemsKey, total, page, itemsPerPage, lastPage.
     */
    public function fromPaginator(LengthAwarePaginator $paginator, string $itemsKey = 'items', ?callable $transform = null): array
    {
        // Lấy các bản ghi của trang hiện tại bọc vào Collection để tiện map.
        $rows = Collection::make($paginator->items());

        // Nếu có hàm biến đổi, áp dụng lên từng dòng rồi đánh lại chỉ số
        // (values()) để mảng JSON là danh sách liên tục thay vì keyed array.
        if ($transform !== null) {
            $rows = $rows->map($transform);
        }

        return [
            // Danh sách bản ghi của trang hiện tại — gắn vào `:items` của bảng.
            $itemsKey => $rows->values()->all(),
            // Tổng số bản ghi toàn bộ (chưa phân trang) — gắn vào `:items-length`.
            'total' => $paginator->total(),
            // Trang hiện tại (bắt đầu từ 1) — đồng bộ với `options.page`.
            'page' => $paginator->currentPage(),
            // Số bản ghi mỗi trang — đồng bộ với `options.itemsPerPage`.
            'itemsPerPage' => $paginator->perPage(),
            // Tổng số trang — tiện hiển thị hoặc điều hướng tuỳ biến.
            'lastPage' => $paginator->lastPage(),
        ];
    }
}
