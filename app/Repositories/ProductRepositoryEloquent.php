<?php

namespace App\Repositories;

use App\Criteria\DataTableCriteria;
use App\Models\Product;
use Prettus\Repository\Eloquent\BaseRepository;

/**
 * Class ProductRepositoryEloquent.
 */
class ProductRepositoryEloquent extends BaseRepository implements ProductRepository
{
    /**
     * Danh sách cột được phép tìm kiếm và lọc qua Criteria.
     *
     * Cột khai báo `like` sẽ tìm gần đúng, các cột còn lại so sánh bằng.
     * Hộp tìm kiếm chung (tham số `q`) chỉ quét các cột `like` (DataTableCriteria
     * tự dựng `searchFields`); cột `=` chỉ đóng vai trò bộ lọc riêng theo cột.
     *
     * @var array
     */
    protected $fieldSearchable = [
        'name' => 'like',
        'price' => 'like',
    ];

    /**
     * Danh sách cột được phép sắp xếp qua tham số `sortBy` của frontend.
     *
     * Tách riêng khỏi `$fieldSearchable` (tìm kiếm/lọc) để sort theo `price`
     * mà không mở kèm việc tìm kiếm/lọc theo cột đó. DataTableCriteria đọc
     * trực tiếp thuộc tính public này làm whitelist — cột lạ sẽ bị bỏ qua
     * thay vì gây lỗi SQL.
     *
     * @var array
     */
    public $fieldSortable = [
        'name',
        'price',
    ];

    /**
     * Khai báo class Model mà repository này quản lý.
     *
     * @return string
     */
    public function model()
    {
        return Product::class;
    }

    /**
     * Khởi động repository: gắn DataTableCriteria để mọi request đi qua
     * index đều được tìm kiếm, lọc theo cột và sắp xếp trong Criteria.
     */
    public function boot()
    {
        $this->pushCriteria(app(DataTableCriteria::class));
    }
}
