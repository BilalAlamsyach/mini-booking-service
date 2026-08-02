<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('route_id')->constrained()->cascadeOnDelete();
            $table->string('vehicle_code', 30);
            $table->date('departure_date');
            $table->time('departure_time');
            $table->time('arrival_time');
            $table->decimal('price', 12, 2);
            $table->timestamps();

            // Satu armada tidak boleh punya dua keberangkatan identik pada rute
            // dan tanggal yang sama.
            $table->unique(['route_id', 'departure_date', 'departure_time'], 'schedules_route_departure_unique');
            $table->index('departure_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedules');
    }
};
