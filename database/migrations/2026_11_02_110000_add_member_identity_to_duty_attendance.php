<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('duty_replacements', function (Blueprint $table): void {
            $table->foreignId('replacement_student_id')
                ->nullable()
                ->after('replacement_user_id')
                ->constrained('students')
                ->restrictOnDelete();
        });

        Schema::table('duty_attendances', function (Blueprint $table): void {
            $table->foreignId('member_user_id')
                ->nullable()
                ->after('student_id')
                ->constrained('users')
                ->restrictOnDelete();
            $table->unique(['duty_schedule_id', 'member_user_id']);
        });
    }

    public function down(): void
    {
        Schema::table('duty_attendances', function (Blueprint $table): void {
            $table->dropUnique(['duty_schedule_id', 'member_user_id']);
            $table->dropConstrainedForeignId('member_user_id');
        });

        Schema::table('duty_replacements', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('replacement_student_id');
        });
    }
};
