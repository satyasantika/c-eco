<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('test_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('participant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('test_config_id')->constrained();
            $table->foreignId('item_bank_id')->constrained();
            $table->char('access_token', 8)->unique();
            $table->bigInteger('rng_seed');
            $table->string('device_uuid')->nullable();
            $table->char('user_agent_hash', 64)->nullable();
            $table->char('ip_hash', 64)->nullable();
            $table->enum('status', ['pending', 'in_progress', 'completed', 'abandoned'])
                ->default('pending');
            $table->decimal('theta', 8, 4)->nullable();
            $table->decimal('se', 8, 4)->nullable();
            $table->unsignedSmallInteger('items_administered')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->string('effective_connection')->nullable();
            $table->timestamps();

            $table->index(['status', 'last_seen_at']);
        });

        Schema::create('session_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('test_session_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('sequence');
            $table->foreignId('item_id')->constrained();
            // R4: tanpa ini data 21 September tidak bisa dianalisis ulang.
            $table->foreignId('item_parameter_id')->constrained();
            $table->decimal('theta_before', 8, 4);
            $table->decimal('se_before', 8, 4);
            $table->decimal('information_at_selection', 12, 6);
            $table->string('selection_rule');
            $table->json('candidate_pool_json')->nullable();
            $table->json('option_permutation_json');
            $table->char('response_label', 1)->nullable();
            $table->boolean('is_correct')->nullable();
            $table->decimal('theta_after', 8, 4)->nullable();
            $table->decimal('se_after', 8, 4)->nullable();
            $table->timestamp('shown_at');
            $table->timestamp('answered_at')->nullable();
            $table->unsignedInteger('latency_ms')->nullable();
            $table->unsignedSmallInteger('retry_count')->default(0);

            $table->unique(['test_session_id', 'sequence']);  // R3
            $table->unique(['test_session_id', 'item_id']);
        });

        Schema::create('session_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('test_session_id')->constrained()->cascadeOnDelete();
            $table->enum('type', [
                'visibility_hidden', 'visibility_visible', 'resume',
                'connection_change', 'retry', 'duplicate_submit',
            ]);
            $table->json('payload_json')->nullable();
            $table->timestamp('occurred_at');

            $table->index(['test_session_id', 'occurred_at']);
        });

        Schema::create('exposure_counters', function (Blueprint $table) {
            $table->foreignId('test_config_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('times_selected')->default(0);
            $table->unsignedInteger('times_administered')->default(0);

            $table->primary(['test_config_id', 'item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exposure_counters');
        Schema::dropIfExists('session_events');
        Schema::dropIfExists('session_items');
        Schema::dropIfExists('test_sessions');
    }
};
