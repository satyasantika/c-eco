<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Menandai gelombang demo yang dibuat tombol "Buat Simulasi" /
        // simulation:generate, supaya reset tidak menyentuh gelombang
        // yang ditulis admin dengan tangan.
        Schema::table('exam_simulations', function (Blueprint $table) {
            $table->boolean('is_demo')->default(false)->after('name')->index();
        });
    }

    public function down(): void
    {
        Schema::table('exam_simulations', function (Blueprint $table) {
            $table->dropIndex(['is_demo']);
            $table->dropColumn('is_demo');
        });
    }
};
