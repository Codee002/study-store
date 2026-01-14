<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Color extends Model
{
    protected $fillable = [
        'color_name',
    ];

// Relationships
    public function products()
    {
        return $this->belongsToMany(Product::class);
    }
}
