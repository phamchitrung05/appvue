<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Prettus\Repository\Contracts\Transformable;
use Prettus\Repository\Traits\TransformableTrait;

/**
 * Class TableZone.
 *
 * Khu vực bàn theo chi nhánh (ví dụ: Trong nhà, Ngoài trời, Tầng 2...).
 * Bàn ăn (DiningTable) tham chiếu tới đây qua zone_id.
 */
class TableZone extends Model implements Transformable
{
    use TransformableTrait;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'is_active',
        'store_id',
    ];

    /**
     * Giá trị mặc định khi tạo mới — cột is_active là NOT NULL không có
     * default ở DB nên phải có mặt trong mọi câu INSERT.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'store_id' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function diningTables(): HasMany
    {
        return $this->hasMany(DiningTable::class, 'zone_id');
    }
}
