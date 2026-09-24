<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('test_sessions', function (Blueprint $table): void {
            $table->string('resume_token', 32)->nullable()->after('opened_at');
        });
    }

    public function down(): void
    {
        Schema::table('test_sessions', function (Blueprint $table): void {
            $table->dropColumn('resume_token');
        });
    }
};
