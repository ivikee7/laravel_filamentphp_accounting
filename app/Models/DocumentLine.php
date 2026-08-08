<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentLine extends Model
{
    protected $fillable = [
        'team_id',
        'document_id',
        'line_no',
        'description',
        'quantity',
        'unit_price',
        'price_includes_tax',
        'tax_rate',
        'tax_rate_id',
        'tax_treatment',
        'tax_amount',
        'tax_breakdown',
        'line_total',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'unit_price' => 'decimal:2',
        'price_includes_tax' => 'boolean',
        'tax_rate' => 'decimal:4',
        'tax_amount' => 'decimal:2',
        'tax_breakdown' => 'array',
        'line_total' => 'decimal:2',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function taxRate(): BelongsTo
    {
        return $this->belongsTo(TaxRate::class);
    }
}
