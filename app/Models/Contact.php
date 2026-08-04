<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Contact extends Model
{
    protected $fillable = [
        'team_id',
        'type',
        'name',
        'email',
        'phone',
        'tax_id',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }
}
