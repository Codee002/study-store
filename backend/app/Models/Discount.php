<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Discount extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'des',
        'percent',
        'status',
        'start_at',
        'end_at',
        'category_id',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function orderDiscounts()
    {
        return $this->hasMany(OrderDiscount::class, 'discount_id');
    }
}
