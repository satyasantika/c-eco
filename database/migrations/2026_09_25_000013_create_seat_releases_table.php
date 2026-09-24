<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Jejak izin pindah HP: siapa melepas kunci kursi, kapan, dan apakah dipakai.
        Schema::create('seat_releases', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('test_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('released_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('previous_token', 32);
            $table->string('reason', 255)->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamp('restored_at')->nullable();
            $table->timestamps();

            $table->index(['test_session_id', 'used_at', 'restored_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seat_releases');
    }
};
