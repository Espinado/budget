<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SecretEntry extends Model
{
    protected $fillable = [
        'secret_group_id',
        'user_id',
        'name',
        'payload',
        'sort',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'encrypted',
        ];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(SecretGroup::class, 'secret_group_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
