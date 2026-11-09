<?php

declare(strict_types=1);

use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\AdditionalPointController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DutyAttendanceController;
use App\Http\Controllers\DutyReplacementController;
use App\Http\Controllers\DutyScheduleController;
use App\Http\Controllers\LatenessController;
use App\Http\Controllers\MorningRewardController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PointLedgerController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\StudentObjectionController;
use App\Http\Controllers\ViolationController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::middleware(['auth', 'role:guru,siswa,operasional'])->get('/dashboard', [DashboardController::class, 'index'])
    ->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('categories/katalog', [CategoryController::class, 'catalog'])
        ->middleware('role:guru,siswa')
        ->name('categories.catalog');

    Route::middleware('role:guru,operasional')->group(function (): void {
        Route::resource('categories', CategoryController::class);
        Route::resource('violations', ViolationController::class)
            ->only(['index', 'create', 'store', 'edit', 'update', 'destroy']);
        Route::resource('lateness', LatenessController::class)
            ->only(['index', 'create', 'store', 'edit', 'update', 'destroy']);
    });

    Route::middleware('role:guru,operasional')->group(function (): void {
        Route::get('duty-attendances', [DutyAttendanceController::class, 'index'])->name('duty-attendances.index');
    });

    Route::middleware('role:operasional')->post('duty-attendances', [DutyAttendanceController::class, 'store'])
        ->name('duty-attendances.store');

    Route::middleware('role:guru,operasional')->group(function (): void {
        Route::get('duty-schedules', [DutyScheduleController::class, 'index'])->name('duty-schedules.index');
        Route::post('duty-schedules/preview', [DutyScheduleController::class, 'preview'])
            ->middleware('can:can_manage_schedule')
            ->name('duty-schedules.preview');
        Route::post('duty-schedules/confirm', [DutyScheduleController::class, 'confirm'])
            ->middleware('can:can_manage_schedule')
            ->name('duty-schedules.confirm');
        Route::get('duty-replacements', [DutyReplacementController::class, 'index'])->name('duty-replacements.index');
        Route::post('duty-replacements', [DutyReplacementController::class, 'store'])->name('duty-replacements.store');
        Route::patch('duty-replacements/{duty_replacement}/decision', [DutyReplacementController::class, 'decide'])
            ->middleware('can:can_manage_schedule')
            ->name('duty-replacements.decide');

        Route::get('morning-rewards', [MorningRewardController::class, 'index'])->name('morning-rewards.index');
        Route::post('morning-rewards', [MorningRewardController::class, 'store'])
            ->name('morning-rewards.store');
        Route::get('morning-rewards/{morning_reward}/edit', [MorningRewardController::class, 'edit'])->name('morning-rewards.edit');
        Route::put('morning-rewards/{morning_reward}', [MorningRewardController::class, 'update'])->name('morning-rewards.update');
        Route::delete('morning-rewards/{morning_reward}', [MorningRewardController::class, 'destroy'])->name('morning-rewards.destroy');
    });

    Route::middleware('role:guru,operasional')->group(function (): void {
        Route::resource('additional-points', AdditionalPointController::class)
            ->only(['index', 'create', 'store', 'edit', 'update', 'destroy']);
    });

    Route::middleware('role:guru')->group(function (): void {
        Route::get('activity-logs', [ActivityLogController::class, 'index'])->name('activity-logs.index');
    });

    Route::middleware('role:guru,operasional')->group(function (): void {
        Route::get('objections', [StudentObjectionController::class, 'index'])->name('objections.index');
        Route::patch('objections/{objection}/decision', [StudentObjectionController::class, 'decide'])
            ->name('objections.decide');
        Route::get('objections/{objection}/evidence', [StudentObjectionController::class, 'evidence'])
            ->name('objections.evidence');

        Route::get('students/export', [StudentController::class, 'export'])->name('students.export');
        Route::get('students/export/excel', [StudentController::class, 'exportExcel'])->name('students.export.excel');
        Route::get('students/import/template', [StudentController::class, 'downloadImportTemplate'])->name('students.import.template');
        Route::post('students/import', [StudentController::class, 'import'])->name('students.import');
        Route::patch('students/{student}/status', [StudentController::class, 'setStatus'])->name('students.status');
        Route::resource('students', StudentController::class)->except(['show']);
        Route::get('ledgers/create', [PointLedgerController::class, 'create'])->name('ledgers.create');
        Route::post('ledgers', [PointLedgerController::class, 'store'])->name('ledgers.store');
        Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('reports/export', [ReportController::class, 'exportCsv'])->name('reports.export.csv');
        Route::get('reports/export/excel', [ReportController::class, 'exportExcel'])->name('reports.export.excel');
        Route::get('reports/print', [ReportController::class, 'printPdf'])->name('reports.print');
    });

    Route::middleware('role:guru,siswa,operasional')->get('students/{student}/transcript', [StudentController::class, 'transcript'])
        ->name('students.transcript');
    Route::middleware('role:guru,siswa,operasional')->get('students/{student}', [StudentController::class, 'show'])->name('students.show');
    Route::get('duty-schedules/preview', [DutyScheduleController::class, 'previewFallback'])
        ->middleware(['auth', 'role:guru,operasional'])
        ->name('duty-schedules.preview-fallback');

    Route::middleware('role:siswa')->group(function (): void {
        Route::get('objections/create', [StudentObjectionController::class, 'create'])->name('objections.create');
        Route::post('objections', [StudentObjectionController::class, 'store'])->name('objections.store');
    });

    Route::middleware('role:guru,siswa,operasional')->group(function (): void {
        Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
        Route::patch('notifications/read-all', [NotificationController::class, 'markAllAsRead'])->name('notifications.read-all');
        Route::patch('notifications/{notification}/read', [NotificationController::class, 'markAsRead'])->name('notifications.read');
    });

    Route::middleware('role:guru,siswa,operasional')->group(function (): void {
        Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    });

    Route::middleware('role:guru,siswa,operasional')->group(function (): void {
        Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    });

    Route::middleware('role:guru,operasional')->group(function (): void {
        Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    });
});

require __DIR__.'/auth.php';
