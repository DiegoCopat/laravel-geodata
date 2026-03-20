<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $table = config('italian-geodata.tables.regions', 'regions');
        $countriesTable = config('italian-geodata.tables.countries', 'countries');

        Schema::create($table, function (Blueprint $table) use ($countriesTable) {
            $table->id();
            $table->foreignId('country_id')->default(1)->constrained($countriesTable)->cascadeOnDelete();
            $table->string('name');
            $table->string('slug')->index();
            $table->string('code', 2)->unique();
            $table->string('geographic_area')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('italian-geodata.tables.regions', 'regions'));
    }
};
