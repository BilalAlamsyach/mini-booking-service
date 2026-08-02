<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabel ini adalah inti dari penanganan race condition.
     *
     * Satu kursi hanya boleh punya SATU baris hold pada satu waktu, apa pun
     * statusnya (`locked` = dikunci sementara, `booked` = sudah dipesan).
     * Constraint `unique(seat_id)` menjadikan aturan itu invariant tingkat
     * database, sehingga dua request konkuren yang lolos pengecekan aplikasi
     * tetap ditolak MySQL dengan duplicate key (SQLSTATE 23000).
     */
    public function up(): void
    {
        Schema::create('seat_holds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seat_id')->constrained()->cascadeOnDelete();
            $table->foreignId('schedule_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('booking_id')->nullable()->constrained()->nullOnDelete();
            $table->uuid('lock_token');
            $table->string('status', 20)->default('locked');
            // NULL untuk hold berstatus `booked` (tidak pernah kedaluwarsa).
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            // Kunci utama pencegahan double-booking.
            $table->unique('seat_id');
            $table->index('lock_token');
            $table->index(['status', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seat_holds');
    }
};
