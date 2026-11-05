<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('violations', function (Blueprint $table): void {
            $table->softDeletes();
        });

        Schema::table('lateness_records', function (Blueprint $table): void {
            $table->dropUnique(['student_id', 'attendance_date']);
            $table->index(['student_id', 'attendance_date']);
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('lateness_records', function (Blueprint $table): void {
            $table->dropIndex(['student_id', 'attendance_date']);
            $table->dropColumn('deleted_at');
            $table->unique(['student_id', 'attendance_date']);
        });

        Schema::table('violations', function (Blueprint $table): void {
            $table->dropColumn('deleted_at');
        });
    }
};
