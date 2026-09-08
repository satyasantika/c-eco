<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('test_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_bank_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->enum('mode', ['adaptive', 'linear'])->default('adaptive');
            $table->unsignedSmallInteger('min_items');
            $table->unsignedSmallInteger('max_items');
            $table->decimal('se_target', 6, 4);
            $table->decimal('theta_prior_mean', 6, 3);
            $table->decimal('theta_prior_sd', 6, 3);
            $table->string('selection_method');
            $table->string('exposure_method');
            $table->unsignedTinyInteger('exposure_k');
            $table->json('content_balancing_json')->nullable();
            $table->boolean('shuffle_options')->default(true);
            $table->boolean('is_active')->default(true);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('test_configs');
    }
};
