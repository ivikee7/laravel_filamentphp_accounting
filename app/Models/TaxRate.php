<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaxRate extends Model
{
    use HasFactory;

    protected $fillable = [
        'team_id',
        'name',
        'code',
        'tax_type',
        'applies_to_scope',
        'category',
        'is_recoverable',
        'rate',
        'components',
        'effective_from',
        'effective_to',
        'is_active',
    ];

    protected $casts = [
        'effective_from' => 'date',
        'effective_to' => 'date',
        'is_recoverable' => 'bool',
        'is_active' => 'bool',
        'components' => 'array',
        'rate' => 'decimal:4',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}
