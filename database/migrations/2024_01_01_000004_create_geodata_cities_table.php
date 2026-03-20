<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $table = config('geodata.tables.cities', 'cities');
        $provincesTable = config('geodata.tables.provinces', 'provinces');

        Schema::create($table, function (Blueprint $table) use ($provincesTable) {
            $table->id();
            $table->foreignId('province_id')->constrained($provincesTable)->cascadeOnDelete();
            $table->string('name');
            $table->string('slug')->index();
            $table->string('belfiore_code', 4)->nullable()->unique();
            $table->string('istat_code', 6)->nullable()->index();
            $table->json('cap')->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lon', 10, 7)->nullable();
            $table->boolean('is_capital')->default(false);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->index(['province_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('geodata.tables.cities', 'cities'));
    }
};
