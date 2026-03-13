<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Evaluate extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'product_id',
        'order_id',
        'rating',
        'content',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function medias()
    {
        return $this->hasMany(EvaluateMedia::class);
    }
}
