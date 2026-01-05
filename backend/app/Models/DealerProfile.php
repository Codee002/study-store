<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DealerProfile extends Model
{
    protected $fillable = [
        'company_name',
        'company_address',
        'tax_code',
    ];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
