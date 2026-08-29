<?php

namespace App\Criteria;

use Illuminate\Http\Request;
use Prettus\Repository\Contracts\RepositoryInterface;
use Prettus\Repository\Criteria\RequestCriteria;

/**
 * Criteria dịch các tham số truy vấn của VDataTableServer (Vuetify) sang đúng
 * tên tham số mà RequestCriteria của l5-repository hiểu được.
 *
 * Frontend gửi lên: `q`, `sortBy`, `orderBy` (chiều sắp xếp), `itemsPerPage`, `page`.
 * RequestCriteria lại đọc: `search`, `orderBy` (tên cột), `sortedBy` (chiều sắp xếp).
 * Vì tên `orderBy` mang nghĩa khác nhau ở hai phía nên bắt buộc phải ánh xạ lại,
 * nếu không cột sắp xếp sẽ nhận giá trị "asc"/"desc" và truy vấn sẽ sai.
 */
class DataTableCriteria extends RequestCriteria
{
    /**
     * Nhận request hiện tại để đọc tham số truy vấn.
     *
     * Được inject tự động khi gọi `app(DataTableCriteria::class)`.
     */
    public function __construct(Request $request)
    {
        parent::__construct($request);
    }

    /**
     * Ánh xạ tham số của Vuetify rồi giao phần dựng truy vấn cho RequestCriteria.
     *
     * Các bước:
     * 1. `q` -> `search` để tìm kiếm theo `$fieldSearchable` của repository.
     * 2. `sortBy` -> `orderBy` (tên cột) và `orderBy` -> `sortedBy` (chiều sắp xếp).
     * 3. Nếu chỉ có chiều sắp xếp mà không có cột thì xoá `orderBy` đi để tránh
     *    việc RequestCriteria sắp xếp theo một cột tên là "asc"/"desc".
     * 4. Áp dụng các filter theo từng cột (vd: `product_group_id`, `is_active`)
     *    dựa trên danh sách `$fieldSearchable` của repository.
     *
     * @param  \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Model  $model
     * @param  RepositoryInterface  $repository  Repository đang áp dụng criteria.
     * @return mixed Builder đã được áp dụng tìm kiếm, lọc và sắp xếp.
     */
    public function apply($model, RepositoryInterface $repository)
    {
        // Luôn đọc request HIỆN TẠI: repository/controller có thể được tái sử
        // dụng giữa các request trong cùng một process (route cache controller
        // trong feature test, Octane...), khiến request inject lúc construct
        // trở thành request cũ — sort/lọc sẽ nhận tham số của request trước.
        $this->request = app(Request::class);

        $this->mapSearchParam();
        $this->mapSortParams($repository);

        $model = parent::apply($model, $repository);

        return $this->applyFieldFilters($model, $repository);
    }

    /**
     * Lọc kết quả theo từng cột khi frontend gửi thẳng tên cột trên query string.
     *
     * Chỉ những cột khai báo trong `$fieldSearchable` của repository mới được
     * phép lọc (đóng vai trò whitelist, tránh việc client tự lọc theo cột lạ).
     * Cột khai báo điều kiện `like` sẽ lọc gần đúng, các cột còn lại so sánh
     * bằng (`=`). Giá trị rỗng hoặc thiếu sẽ bị bỏ qua.
     *
     * Ví dụ: `?product_group_id=3&is_active=1` sinh ra
     * `WHERE product_group_id = 3 AND is_active = 1`.
     *
     * @param  \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Model  $model
     * @param  RepositoryInterface  $repository  Repository chứa danh sách cột được phép lọc.
     * @return mixed Builder đã được gắn thêm các điều kiện lọc.
     */
    protected function applyFieldFilters($model, RepositoryInterface $repository)
    {
        $fieldsSearchable = (array) $repository->getFieldsSearchable();

        foreach ($fieldsSearchable as $field => $condition) {
            if (is_int($field)) {
                $field = $condition;
                $condition = '=';
            }

            if (! $this->request->filled($field)) {
                continue;
            }

            $value = (string) $this->request->query($field);

            // Chuẩn hoá giá trị boolean dạng chữ gửi từ query string
            // ('true'/'false' thành '1'/'0') để so sánh đúng với cột boolean
            // trên mọi driver DB — SQLite so sánh chặt kiểu nên 'true'
            // không bao giờ khớp với giá trị 1 trong DB.
            if (in_array(strtolower($value), ['true', 'false'], true)) {
                $value = strtolower($value) === 'true' ? '1' : '0';
            }

            $condition = strtolower(trim((string) $condition));

            if (in_array($condition, ['like', 'ilike'], true)) {
                $model = $model->where($field, $condition, "%{$value}%");
            } else {
                $model = $model->where($field, '=', $value);
            }
        }

        return $model;
    }

    /**
     * Chuyển tham số tìm kiếm `q` của frontend thành `search` của RequestCriteria.
     *
     * Chỉ ghi đè khi `q` có giá trị thực, để request nào đã gửi sẵn `search`
     * vẫn hoạt động bình thường.
     */
    protected function mapSearchParam(): void
    {
        if (! $this->request->filled('q')) {
            return;
        }

        $this->request->query->set('search', (string) $this->request->query('q'));
    }

    /**
     * Chuyển cặp `sortBy` / `orderBy` của Vuetify thành `orderBy` / `sortedBy`.
     *
     * Chiều sắp xếp được chuẩn hoá về chữ thường và chỉ chấp nhận `asc` hoặc
     * `desc`; giá trị lạ sẽ mặc định thành `asc`.
     *
     * Cột sắp xếp phải nằm trong whitelist của repository (`$fieldSortable`,
     * fallback về `$fieldSearchable`) — RequestCriteria của l5-repository đẩy
     * thẳng cột vào ORDER BY nên cột lạ sẽ gây lỗi SQL; ở đây loại bỏ tham số
     * thay vì để truy vấn hỏng.
     */
    protected function mapSortParams(RepositoryInterface $repository): void
    {
        $direction = strtolower((string) $this->request->query('orderBy'));
        $isValidDirection = in_array($direction, ['asc', 'desc'], true);

        if ($this->request->filled('sortBy')) {
            $sortColumn = (string) $this->request->query('sortBy');

            if (! $this->isSortableColumn($repository, $sortColumn)) {
                $this->request->query->remove('orderBy');

                return;
            }

            $this->request->query->set('orderBy', $sortColumn);
            $this->request->query->set('sortedBy', $isValidDirection ? $direction : 'asc');

            return;
        }

        if ($isValidDirection) {
            $this->request->query->remove('orderBy');
        }
    }

    /**
     * Kiểm tra cột sắp xếp có được repository cho phép hay không.
     *
     * Ưu tiên thuộc tính `$fieldSortable` của repository nếu có — cho phép
     * sắp xếp theo cột không nằm trong danh sách tìm kiếm/lọc (vd `price`);
     * ngược lại rơi về các cột khai báo trong `$fieldSearchable`.
     */
    protected function isSortableColumn(RepositoryInterface $repository, string $column): bool
    {
        $sortable = property_exists($repository, 'fieldSortable')
            ? (array) $repository->fieldSortable
            : array_keys((array) $repository->getFieldsSearchable());

        return in_array($column, $sortable, true);
    }
}
