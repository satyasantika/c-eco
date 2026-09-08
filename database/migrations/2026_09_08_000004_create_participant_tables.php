<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schools', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('city')->nullable();
        });

        Schema::create('participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('class_name');
            $table->string('student_code');
            $table->string('display_name');
            $table->string('sex')->nullable();
            $table->timestamp('consent_at')->nullable();
            $table->timestamps();

            $table->unique(['school_id', 'student_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('participants');
        Schema::dropIfExists('schools');
    }
};
