<?php

namespace DiegoCopat\ItalianGeodata\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class City extends Model
{
    protected $fillable = [
        'province_id',
        'name',
        'slug',
        'belfiore_code',
        'istat_code',
        'cap',
        'lat',
        'lon',
        'is_capital',
        'is_active',
    ];

    protected $casts = [
        'cap' => 'array',
        'lat' => 'decimal:7',
        'lon' => 'decimal:7',
        'is_capital' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function getTable(): string
    {
        return config('italian-geodata.tables.cities', 'cities');
    }

    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByBelfiore($query, string $code)
    {
        return $query->where('belfiore_code', $code);
    }

    public function scopeByProvince($query, string $provinceCode)
    {
        return $query->whereHas('province', fn ($q) => $q->where('code', strtoupper($provinceCode)));
    }

    public function scopeCapitals($query)
    {
        return $query->where('is_capital', true);
    }
}
