<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Price extends Model
{
    protected $fillable = [
        'product_id',
        'tier_id',
        'min_quantity',
        'price',
    ];

    // Relationships
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function tier()
    {
        return $this->belongsTo(Tier::class);
    }
}
