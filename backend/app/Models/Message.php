<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Message extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'conversation_id',
        'user_id',
        'content',
        'type',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function medias(): HasMany
    {
        return $this->hasMany(MessageMedia::class);
    }

    public function reads(): HasMany
    {
        return $this->hasMany(MessageRead::class);
    }
}
