<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Table Names
    |--------------------------------------------------------------------------
    |
    | Customize the table names used by the package. Useful if you already
    | have tables with the same names in your application.
    |
    */

    'tables' => [
        'countries' => 'countries',
        'regions' => 'regions',
        'provinces' => 'provinces',
        'cities' => 'cities',
    ],

    /*
    |--------------------------------------------------------------------------
    | Include Historical Countries
    |--------------------------------------------------------------------------
    |
    | When false, GeoData::countries() returns only currently active countries.
    | Historical countries (USSR, Yugoslavia, etc.) are always available via
    | GeoData::findByBelfiore() for fiscal code lookups.
    |
    */

    'include_historical_countries' => false,

];
