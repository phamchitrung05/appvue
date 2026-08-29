<?php

namespace App\Repositories;

use App\Criteria\DataTableCriteria;
use App\Models\ProductGroup;
use Prettus\Repository\Eloquent\BaseRepository;

/**
 * Class ProductGroupRepositoryEloquent.
 */
class ProductGroupRepositoryEloquent extends BaseRepository implements ProductGroupRepository
{
    /**
     * Danh sách cột được phép tìm kiếm và lọc qua Criteria.
     *
     * Cột khai báo `like` sẽ tìm gần đúng, các cột còn lại so sánh bằng.
     *
     * @var array
     */
    protected $fieldSearchable = [
        'name' => 'like',
        'store_id',
        'is_active',
    ];

    /**
     * Danh sách cột được phép sắp xếp qua tham số `sortBy` của frontend
     * (đọc bởi DataTableCriteria — cột lạ sẽ bị bỏ qua thay vì lỗi SQL).
     *
     * @var array
     */
    public $fieldSortable = [
        'name',
        'sort_order',
        'is_active',
    ];

    /**
     * Khai báo class Model mà repository này quản lý.
     *
     * @return string
     */
    public function model()
    {
        return ProductGroup::class;
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
