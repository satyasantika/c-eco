<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dimensions', function (Blueprint $table) {
            $table->id();
            $table->enum('code', [
                'fluency', 'flexibility', 'originality', 'elaboration',
                'solutif', 'adaptif', 'prediktif',
            ])->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedTinyInteger('display_order')->default(0);
        });

        Schema::create('item_banks', function (Blueprint $table) {
            $table->id();
            $table->enum('grade', ['X', 'XI', 'XII']);
            $table->string('version');
            $table->string('irt_model');
            $table->text('scale_note')->nullable();
            $table->unsignedInteger('calibration_n')->nullable();
            $table->string('calibration_source')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['grade', 'version']);
        });

        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_bank_id')->constrained()->cascadeOnDelete();
            $table->string('code')->unique(); // R7: identitas butir
            $table->foreignId('dimension_id')->constrained();
            $table->text('learning_objective')->nullable();
            $table->string('topic')->nullable();
            $table->string('semester')->nullable();
            $table->text('indicator')->nullable();
            $table->string('bloom_level')->nullable();
            $table->longText('stem_html');
            $table->string('media_path')->nullable();
            $table->enum('status', ['active', 'retired'])->default('active');
            $table->text('source_note')->nullable();
            $table->timestamps();

            $table->index(['item_bank_id', 'status']);
        });

        Schema::create('item_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->enum('label', ['A', 'B', 'C', 'D', 'E']);
            $table->text('body_html');
            $table->boolean('is_key')->default(false);
            $table->unsignedTinyInteger('display_order');

            $table->unique(['item_id', 'label']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_options');
        Schema::dropIfExists('items');
        Schema::dropIfExists('item_banks');
        Schema::dropIfExists('dimensions');
    }
};
