<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TaxProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'team_id',
        'country_code',
        'currency_code',
        'tax_precision',
        'rounding_mode',
        'prices_include_tax',
    ];

    protected $casts = [
        'prices_include_tax' => 'bool',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function taxRates(): HasMany
    {
        return $this->hasMany(TaxRate::class, 'team_id', 'team_id');
    }
}
