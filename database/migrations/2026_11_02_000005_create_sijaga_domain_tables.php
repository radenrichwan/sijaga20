<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('violations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->foreignId('violation_category_id')->constrained('violation_categories')->restrictOnDelete();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('point_transaction_id')->nullable()->constrained('point_transactions')->restrictOnDelete();
            $table->date('violation_date')->index();
            $table->text('description')->nullable();
            $table->timestamps();
            $table->index(['student_id', 'violation_date']);
        });

        Schema::create('lateness_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->date('attendance_date');
            $table->time('arrival_time')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
            $table->unique(['student_id', 'attendance_date']);
        });

        Schema::create('morning_rewards', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->date('reward_date');
            $table->time('arrival_time');
            $table->unsignedTinyInteger('rank');
            $table->timestamps();
            $table->unique(['student_id', 'reward_date']);
            $table->unique(['reward_date', 'rank']);
        });

        Schema::create('duty_schedules', function (Blueprint $table): void {
            $table->id();
            $table->date('week_start')->index();
            $table->date('duty_date')->unique();
            $table->string('status', 20)->default('scheduled');
            $table->text('cancellation_reason')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('cancelled_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('duty_schedule_members', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('duty_schedule_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['duty_schedule_id', 'user_id']);
        });

        Schema::create('duty_replacements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('duty_schedule_id')->constrained()->restrictOnDelete();
            $table->foreignId('original_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('replacement_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->text('reason');
            $table->string('status', 30)->default('pending');
            $table->text('rejection_reason')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('decided_at')->nullable();
            $table->timestamps();
            $table->index(['duty_schedule_id', 'status']);
        });

        Schema::create('duty_attendances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('duty_schedule_id')->constrained()->restrictOnDelete();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('point_transaction_id')->nullable()->constrained('point_transactions')->restrictOnDelete();
            $table->timestampTz('attended_at')->useCurrent();
            $table->timestamps();
            $table->unique(['duty_schedule_id', 'student_id']);
        });

        Schema::create('additional_points', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->foreignId('given_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('point_transaction_id')->nullable()->constrained('point_transactions')->restrictOnDelete();
            $table->unsignedInteger('points');
            $table->date('awarded_date');
            $table->string('reason', 255);
            $table->timestamps();
        });

        Schema::create('student_objections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->string('source_type', 40);
            $table->unsignedBigInteger('source_id');
            $table->text('reason');
            $table->string('status', 30)->default('pending');
            $table->text('decision_reason')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('decided_at')->nullable();
            $table->timestamps();
            $table->index(['source_type', 'source_id']);
            $table->index(['student_id', 'status']);
        });

        Schema::create('activity_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('role_name', 30);
            $table->string('action', 100);
            $table->string('subject_type', 100)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('before_data')->nullable();
            $table->json('after_data')->nullable();
            $table->json('metadata')->nullable();
            $table->timestampTz('occurred_at')->useCurrent()->index();
            $table->timestamps();
            $table->index(['user_id', 'action']);
            $table->index(['subject_type', 'subject_id']);
        });

        Schema::create('notifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->index(['notifiable_type', 'notifiable_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('student_objections');
        Schema::dropIfExists('additional_points');
        Schema::dropIfExists('duty_attendances');
        Schema::dropIfExists('duty_replacements');
        Schema::dropIfExists('duty_schedule_members');
        Schema::dropIfExists('duty_schedules');
        Schema::dropIfExists('morning_rewards');
        Schema::dropIfExists('lateness_records');
        Schema::dropIfExists('violations');
    }
};
