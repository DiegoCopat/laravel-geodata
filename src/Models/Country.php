<?php

namespace DiegoCopat\LaravelGeodata\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Country extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'iso_code',
        'belfiore_code',
        'citizenship',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function getTable(): string
    {
        return config('geodata.tables.countries', 'countries');
    }

    public function regions(): HasMany
    {
        return $this->hasMany(Region::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByBelfiore($query, string $code)
    {
        return $query->where('belfiore_code', $code);
    }
}
