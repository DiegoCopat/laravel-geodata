<?php

namespace DiegoCopat\ItalianGeodata\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Region extends Model
{
    protected $fillable = [
        'country_id',
        'name',
        'slug',
        'code',
        'geographic_area',
    ];

    public function getTable(): string
    {
        return config('italian-geodata.tables.regions', 'regions');
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function provinces(): HasMany
    {
        return $this->hasMany(Province::class);
    }

    public function cities(): HasManyThrough
    {
        return $this->hasManyThrough(City::class, Province::class);
    }
}
