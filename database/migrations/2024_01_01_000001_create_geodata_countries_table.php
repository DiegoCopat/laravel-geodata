<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $table = config('italian-geodata.tables.countries', 'countries');

        Schema::create($table, function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->index();
            $table->string('iso_code', 10)->nullable()->index();
            $table->string('belfiore_code', 4)->unique();
            $table->string('citizenship')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('italian-geodata.tables.countries', 'countries'));
    }
};
