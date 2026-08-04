<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JournalEntry extends Model
{
    protected $fillable = [
        'team_id',
        'fiscal_period_id',
        'entry_no',
        'entry_date',
        'source_type',
        'source_id',
        'status',
        'description',
        'debit_total',
        'credit_total',
        'created_by',
    ];

    protected $casts = [
        'entry_date' => 'date',
        'debit_total' => 'decimal:2',
        'credit_total' => 'decimal:2',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function fiscalPeriod(): BelongsTo
    {
        return $this->belongsTo(FiscalPeriod::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class);
    }
}
