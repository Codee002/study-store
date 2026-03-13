<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EvaluateMedia extends Model
{
    protected $table = 'evaluate_medias';

    protected $fillable = [
        'evaluate_id',
        'type',
        'url',
        'public_id',
    ];

    public function evaluate()
    {
        return $this->belongsTo(Evaluate::class);
    }
}
