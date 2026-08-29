<?php

namespace App\Repositories;

use App\Models\OrderItem;
use Prettus\Repository\Criteria\RequestCriteria;
use Prettus\Repository\Eloquent\BaseRepository;

/**
 * Class OrderItemRepositoryEloquent.
 */
class OrderItemRepositoryEloquent extends BaseRepository implements OrderItemRepository
{
    /**
     * Fields that RequestCriteria can search on (?search=...).
     *
     * @var array
     */
    protected $fieldSearchable = [
        'notes' => 'like',
        'order_id',
        'product_id',
    ];

    /**
     * Specify Model class name
     *
     * @return string
     */
    public function model()
    {
        return OrderItem::class;
    }

    /**
     * Boot up the repository, pushing criteria
     */
    public function boot()
    {
        $this->pushCriteria(app(RequestCriteria::class));
    }
}
