<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('nis', 20)->unique();
            $table->string('nisn')->unique();
            $table->string('gender', 20)->nullable();
            $table->string('grade', 5)->nullable();
            $table->string('major', 100)->nullable();
            $table->string('class_name');
            $table->boolean('is_active')->default(true);
            $table->integer('initial_points')->default(100);
            $table->integer('current_points')->default(100);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
