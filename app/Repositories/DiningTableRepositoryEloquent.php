<?php

namespace App\Repositories;

use App\Criteria\DataTableCriteria;
use App\Models\DiningTable;
use Prettus\Repository\Eloquent\BaseRepository;

/**
 * Class DiningTableRepositoryEloquent.
 */
class DiningTableRepositoryEloquent extends BaseRepository implements DiningTableRepository
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
    ];

    /**
     * Khai báo class Model mà repository này quản lý.
     *
     * @return string
     */
    public function model()
    {
        return DiningTable::class;
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
