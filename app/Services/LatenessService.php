<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\LatenessRecord;
use App\Models\PointTransaction;
use App\Models\Student;
use App\Models\User;
use App\Models\ViolationCategory;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LatenessService
{
    public function __construct(
        private readonly PointTransactionService $transactions,
        private readonly ActivityLogService $activityLogs,
        private readonly InternalNotificationService $notifications,
    ) {}

    public function create(array $data, User $actor): LatenessRecord
    {
        return DB::transaction(function () use ($data, $actor): LatenessRecord {
            $student = Student::query()
                ->where('is_active', true)
                ->where('nis', $data['student_nis'])
                ->lockForUpdate()
                ->firstOrFail();

            if (LatenessRecord::withTrashed()
                ->where('student_id', $student->id)
                ->whereDate('attendance_date', $data['attendance_date'])
                ->whereNull('deleted_at')
                ->exists()) {
                throw ValidationException::withMessages([
                    'student_nis' => 'Siswa ini sudah memiliki catatan keterlambatan pada tanggal tersebut.',
                ]);
            }

            $category = $this->latenessCategory();
            $record = LatenessRecord::query()->create([
                'student_id' => $student->id,
                'recorded_by' => $actor->id,
                'attendance_date' => $data['attendance_date'],
                'arrival_time' => $data['arrival_time'],
                'description' => $data['description'] ?? null,
            ]);
            $transaction = $this->transactions->record(
                $student,
                $category,
                $actor,
                'lateness_record',
                $record->id,
                -5,
                $this->description($data['description'] ?? null),
                $record->attendance_date->toDateString(),
            );
            $record->update(['point_transaction_id' => $transaction->id]);
            $this->activityLogs->record($actor, 'create', 'lateness_record', $record, null, [
                'student_id' => $student->id,
                'attendance_date' => $record->attendance_date->toDateString(),
                'arrival_time' => $record->arrival_time,
                'points' => -5,
            ]);
            $this->notifications->send(
                [$student->user],
                'lateness.created',
                'Catatan keterlambatan baru',
                'Keterlambatan Anda dicatat dan saldo poin berkurang 5.',
                route('objections.create'),
                ['lateness_record_id' => $record->id, 'points' => -5],
            );

            return $record;
        });
    }

    public function update(LatenessRecord $record, array $data, User $actor): void
    {
        DB::transaction(function () use ($record, $data, $actor): void {
            $student = Student::query()->whereKey($record->student_id)->lockForUpdate()->firstOrFail();
            $record = LatenessRecord::query()->whereKey($record->id)->lockForUpdate()->firstOrFail();

            if (LatenessRecord::query()
                ->where('student_id', $student->id)
                ->whereDate('attendance_date', $data['attendance_date'])
                ->whereKeyNot($record->id)
                ->exists()) {
                throw ValidationException::withMessages([
                    'attendance_date' => 'Siswa ini sudah memiliki catatan keterlambatan pada tanggal tersebut.',
                ]);
            }

            $oldTransaction = $this->currentTransaction($record);
            $oldValues = $record->only(['attendance_date', 'arrival_time', 'description']);
            $category = $oldTransaction->category()->firstOrFail();
            $this->reverse($student, $record, $oldTransaction, $category, $actor, 'Koreksi perubahan keterlambatan #'.$record->id.'.');
            $record->fill([
                'attendance_date' => $data['attendance_date'],
                'arrival_time' => $data['arrival_time'],
                'description' => $data['description'] ?? null,
            ])->save();
            $transaction = $this->transactions->record(
                $student,
                $category,
                $actor,
                'lateness_record',
                $record->id,
                -5,
                $this->description($data['description'] ?? null),
                $record->attendance_date->toDateString(),
                $oldTransaction->id,
            );
            $record->update(['point_transaction_id' => $transaction->id]);
            $this->activityLogs->record($actor, 'update', 'lateness_record', $record, $oldValues, [
                'attendance_date' => $record->attendance_date->toDateString(),
                'arrival_time' => $record->arrival_time,
                'description' => $record->description,
                'points' => -5,
            ]);
        });
    }

    public function delete(LatenessRecord $record, User $actor): void
    {
        DB::transaction(function () use ($record, $actor): void {
            $student = Student::query()->whereKey($record->student_id)->lockForUpdate()->firstOrFail();
            $record = LatenessRecord::query()->whereKey($record->id)->lockForUpdate()->firstOrFail();
            $oldTransaction = $this->currentTransaction($record);
            $oldValues = $record->only(['student_id', 'attendance_date', 'arrival_time', 'description']);
            $this->reverse(
                $student,
                $record,
                $oldTransaction,
                $oldTransaction->category()->firstOrFail(),
                $actor,
                'Koreksi penghapusan keterlambatan #'.$record->id.'.',
            );
            $this->activityLogs->record($actor, 'delete', 'lateness_record', $record, $oldValues);
            $record->delete();
        });
    }

    private function currentTransaction(LatenessRecord $record): PointTransaction
    {
        if ($record->point_transaction_id === null) {
            throw ValidationException::withMessages([
                'lateness' => 'Transaksi poin terkait tidak ditemukan; perubahan tidak dapat diterapkan dengan aman.',
            ]);
        }

        return PointTransaction::query()->whereKey($record->point_transaction_id)->lockForUpdate()->firstOrFail();
    }

    private function reverse(
        Student $student,
        LatenessRecord $record,
        PointTransaction $transaction,
        ViolationCategory $category,
        User $actor,
        string $description,
    ): void {
        $this->transactions->record(
            $student,
            $category,
            $actor,
            'lateness_record',
            $record->id,
            -$transaction->points,
            $description,
            today()->toDateString(),
            $transaction->id,
        );
    }

    private function latenessCategory(): ViolationCategory
    {
        return ViolationCategory::query()
            ->where('type', 'violation')
            ->where('name', 'Terlambat')
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function description(?string $description): string
    {
        return 'Terlambat'.($description ? ': '.$description : '');
    }
}
