<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('simulation_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('test_config_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('n_replications');
            $table->string('theta_distribution');
            $table->bigInteger('seed');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
        });

        Schema::create('simulation_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('simulation_run_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('replication');
            $table->decimal('true_theta', 8, 4);
            $table->decimal('est_theta', 8, 4);
            $table->decimal('se', 8, 4);
            $table->unsignedSmallInteger('n_items');
            $table->json('items_json');

            $table->index(['simulation_run_id', 'true_theta']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('simulation_results');
        Schema::dropIfExists('simulation_runs');
    }
};
