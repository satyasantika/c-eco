<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calibration_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_bank_id')->constrained()->cascadeOnDelete();
            $table->string('software');
            $table->string('version')->nullable();
            $table->unsignedInteger('sample_n')->nullable();
            $table->string('method')->nullable();
            $table->boolean('is_provisional')->default(false);
            $table->text('notes')->nullable();
            $table->timestamp('run_at');
        });

        // R5: parameter berversi. Setiap kalibrasi menyisipkan baris baru dan
        // menonaktifkan yang lama; baris lama tidak pernah di-UPDATE atau dihapus,
        // jadi tidak ada unique constraint pada item_id di sini.
        Schema::create('item_parameters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('calibration_run_id')->constrained();
            $table->string('model');
            $table->decimal('a', 8, 4);
            $table->decimal('b', 8, 4);
            $table->decimal('c', 8, 4);
            $table->decimal('se_a', 8, 4)->nullable();
            $table->decimal('se_b', 8, 4)->nullable();
            $table->decimal('infit', 8, 4)->nullable();
            $table->decimal('outfit', 8, 4)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('calibrated_at');

            $table->index(['item_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_parameters');
        Schema::dropIfExists('calibration_runs');
    }
};
