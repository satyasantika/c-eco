<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_simulations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedSmallInteger('students');
            $table->unsignedTinyInteger('rooms');
            $table->timestamp('starts_at');
            $table->unsignedTinyInteger('grade_share_x');
            $table->unsignedTinyInteger('grade_share_xi');
            $table->unsignedTinyInteger('grade_share_xii');
            $table->unsignedSmallInteger('pool_size');
            $table->unsignedTinyInteger('operators_count');
            $table->unsignedTinyInteger('pengawas_count');
            $table->string('plain_password');
            $table->timestamps();
        });

        Schema::table('schools', function (Blueprint $table) {
            $table->foreignId('exam_simulation_id')->nullable()->after('id')->constrained()->nullOnDelete();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('exam_simulation_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->index('exam_simulation_id');
        });

        Schema::table('test_configs', function (Blueprint $table) {
            $table->foreignId('exam_simulation_id')->nullable()->after('id')->constrained()->nullOnDelete();
        });

        Schema::table('exam_groups', function (Blueprint $table) {
            $table->foreignId('exam_simulation_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->index('exam_simulation_id');
        });
    }

    public function down(): void
    {
        Schema::table('exam_groups', function (Blueprint $table) {
            $table->dropConstrainedForeignId('exam_simulation_id');
        });
        Schema::table('test_configs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('exam_simulation_id');
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('exam_simulation_id');
        });
        Schema::table('schools', function (Blueprint $table) {
            $table->dropConstrainedForeignId('exam_simulation_id');
        });
        Schema::dropIfExists('exam_simulations');
    }
};
