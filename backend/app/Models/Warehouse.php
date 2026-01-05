<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Warehouse extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'name',
        'address',
        'capacity',
    ];
    // Relationships
    public function receipts()
    {
        return $this->hasMany(Receipt::class);
    }

    public function warehouseDetails()
    {
        return $this->hasMany(WarehouseDetail::class);
    }
}
