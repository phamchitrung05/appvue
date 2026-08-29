<?php

namespace App\Repositories;

use App\Models\Printer;
use Prettus\Repository\Criteria\RequestCriteria;
use Prettus\Repository\Eloquent\BaseRepository;

/**
 * Class PrinterRepositoryEloquent.
 */
class PrinterRepositoryEloquent extends BaseRepository implements PrinterRepository
{
    /**
     * Fields that RequestCriteria can search on (?search=...).
     *
     * @var array
     */
    protected $fieldSearchable = [
        'name' => 'like',
        'printer_type' => 'like',
        'ip_address' => 'like',
        'store_id',
        'is_active',
    ];

    /**
     * Specify Model class name
     *
     * @return string
     */
    public function model()
    {
        return Printer::class;
    }

    /**
     * Boot up the repository, pushing criteria
     */
    public function boot()
    {
        $this->pushCriteria(app(RequestCriteria::class));
    }
}
