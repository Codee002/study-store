<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DealerProfile extends Model
{
    protected $fillable = [
        'user_id',
        'tier_id',
        'status',
        'company_name',
        'company_address',
        'tax_code',
    ];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function tier()
    {
        return $this->belongsTo(Tier::class);
    }
}
