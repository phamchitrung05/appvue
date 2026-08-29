<?php

namespace App\Repositories;

use App\Criteria\DataTableCriteria;
use App\Models\Store;
use Prettus\Repository\Eloquent\BaseRepository;

/**
 * Class StoreRepositoryEloquent.
 */
class StoreRepositoryEloquent extends BaseRepository implements StoreRepository
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
        'address' => 'like',
        'phone' => 'like',
        'email' => 'like',
        'is_active',
    ];

    /**
     * Khai báo class Model mà repository này quản lý.
     *
     * @return string
     */
    public function model()
    {
        return Store::class;
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
