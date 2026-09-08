<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('test_config_items', function (Blueprint $table) {
            $table->foreignId('test_config_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();

            $table->primary(['test_config_id', 'item_id']);
        });

        Schema::create('exam_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('room');
            $table->timestamp('starts_at');
            $table->foreignId('supervisor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('test_config_id')->constrained();
            $table->unsignedSmallInteger('capacity');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['school_id', 'room', 'starts_at']);
            $table->index(['supervisor_id', 'starts_at']);
        });

        Schema::table('test_sessions', function (Blueprint $table) {
            $table->foreignId('exam_group_id')->nullable()->after('item_bank_id')->constrained()->nullOnDelete();
            $table->timestamp('claimed_at')->nullable()->after('status');

            $table->index(['exam_group_id', 'claimed_at']);
        });
    }

    public function down(): void
    {
        Schema::table('test_sessions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('exam_group_id');
            $table->dropColumn('claimed_at');
        });

        Schema::dropIfExists('exam_groups');
        Schema::dropIfExists('test_config_items');
    }
};
