<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tier extends Model
{
    protected $fillable = [
        'name',
        'code',
        'default',
        'status',
    ];

    // Relationships
    public function users()
    {
        return $this->hasMany(User::class);
    }
}
