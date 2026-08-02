<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('routes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('operator_id')->constrained()->cascadeOnDelete();
            $table->string('origin', 100);
            $table->string('destination', 100);
            $table->unsignedSmallInteger('duration_minutes');
            $table->timestamps();

            // Mendukung pencarian jadwal berdasarkan pasangan kota asal-tujuan.
            $table->index(['origin', 'destination']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('routes');
    }
};
