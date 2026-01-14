<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WarehouseDetail extends Model
{
    protected $fillable = [
        'warehouse_id',
        'product_id',
        'color_id',
        'quantity',
        'status',
    ];

    // Relationships
    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function color()
    {
        return $this->belongsTo(Color::class);
    }
}
