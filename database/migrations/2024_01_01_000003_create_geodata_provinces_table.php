<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $table = config('geodata.tables.provinces', 'provinces');
        $regionsTable = config('geodata.tables.regions', 'regions');

        Schema::create($table, function (Blueprint $table) use ($regionsTable) {
            $table->id();
            $table->foreignId('region_id')->constrained($regionsTable)->cascadeOnDelete();
            $table->string('name');
            $table->string('slug')->index();
            $table->string('code', 3)->unique();
            $table->string('type')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('geodata.tables.provinces', 'provinces'));
    }
};
