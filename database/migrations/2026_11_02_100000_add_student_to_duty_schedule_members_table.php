<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('duty_schedule_members', function (Blueprint $table): void {
            $table->foreignId('student_id')
                ->nullable()
                ->after('user_id')
                ->constrained('students')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('duty_schedule_members', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('student_id');
        });
    }
};
