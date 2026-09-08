<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('test_sessions', function (Blueprint $table) {
            $table->timestamp('opened_at')->nullable()->after('claimed_at');
            $table->index(['exam_group_id', 'opened_at']);
        });

        Schema::table('test_configs', function (Blueprint $table) {
            $table->unsignedTinyInteger('grade_share_x')->nullable()->after('is_active');
            $table->unsignedTinyInteger('grade_share_xi')->nullable()->after('grade_share_x');
            $table->unsignedTinyInteger('grade_share_xii')->nullable()->after('grade_share_xi');
            $table->unsignedSmallInteger('pool_size')->nullable()->after('grade_share_xii');
        });
    }

    public function down(): void
    {
        Schema::table('test_sessions', function (Blueprint $table) {
            $table->dropIndex(['exam_group_id', 'opened_at']);
            $table->dropColumn('opened_at');
        });

        Schema::table('test_configs', function (Blueprint $table) {
            $table->dropColumn(['grade_share_x', 'grade_share_xi', 'grade_share_xii', 'pool_size']);
        });
    }
};
