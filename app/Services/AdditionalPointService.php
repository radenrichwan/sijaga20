<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AdditionalPoint;
use App\Models\PointTransaction;
use App\Models\Student;
use App\Models\User;
use App\Models\ViolationCategory;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdditionalPointService
{
    public function __construct(
        private readonly PointTransactionService $transactions,
        private readonly ActivityLogService $activityLogs,
        private readonly InternalNotificationService $notifications,
    ) {}

    public function create(array $data, User $actor): AdditionalPoint
    {
        return DB::transaction(function () use ($data, $actor): AdditionalPoint {
            $student = Student::query()
                ->where('is_active', true)
                ->where('nis', $data['student_nis'])
                ->lockForUpdate()
                ->firstOrFail();
            $point = AdditionalPoint::query()->create([
                'student_id' => $student->id,
                'given_by' => $actor->id,
                'points' => $data['points'],
                'awarded_date' => $data['awarded_date'],
                'reason' => $data['reason'],
            ]);
            $transaction = $this->transactions->record(
                $student,
                $this->category(),
                $actor,
                'additional_point',
                $point->id,
                (int) $point->points,
                $point->reason,
                $point->awarded_date->toDateString(),
            );
            $point->update(['point_transaction_id' => $transaction->id]);
            $this->activityLogs->record($actor, 'create', 'additional_point', $point, null, [
                'student_id' => $student->id,
                'points' => $point->points,
                'awarded_date' => $point->awarded_date->toDateString(),
                'reason' => $point->reason,
            ]);
            $this->notifications->send(
                [$student->user],
                'additional_point.created',
                'Poin tambahan',
                'Guru memberikan '.$point->points.' poin tambahan kepada Anda.',
                route('students.show', $student),
                ['additional_point_id' => $point->id, 'points' => $point->points],
            );

            return $point;
        });
    }

    public function update(AdditionalPoint $point, array $data, User $actor): void
    {
        DB::transaction(function () use ($point, $data, $actor): void {
            $point = AdditionalPoint::query()->whereKey($point->id)->lockForUpdate()->firstOrFail();
            $oldTransaction = $this->currentTransaction($point);
            $targetStudentId = Student::query()
                ->where('is_active', true)
                ->where('nis', $data['student_nis'])
                ->valueOrFail('id');
            $studentIds = collect([$point->student_id, $targetStudentId])
                ->unique()
                ->sort()
                ->values();
            $students = Student::query()->whereIn('id', $studentIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $oldStudent = $students->get($point->student_id);
            $student = $students->get((int) $targetStudentId);
            $oldValues = $point->only(['student_id', 'points', 'awarded_date', 'reason']);

            $this->reverse($oldStudent, $point, $oldTransaction, $actor, 'Koreksi perubahan tambahan poin #'.$point->id.'.');
            $point->fill([
                'student_id' => $student->id,
                'given_by' => $actor->id,
                'points' => $data['points'],
                'awarded_date' => $data['awarded_date'],
                'reason' => $data['reason'],
            ])->save();
            $transaction = $this->transactions->record(
                $student,
                $this->category(),
                $actor,
                'additional_point',
                $point->id,
                (int) $point->points,
                $point->reason,
                $point->awarded_date->toDateString(),
                $oldTransaction->id,
            );
            $point->update(['point_transaction_id' => $transaction->id]);
            $this->activityLogs->record($actor, 'update', 'additional_point', $point, $oldValues, [
                'student_id' => $student->id,
                'points' => $point->points,
                'awarded_date' => $point->awarded_date->toDateString(),
                'reason' => $point->reason,
            ]);
        });
    }

    public function delete(AdditionalPoint $point, User $actor): void
    {
        DB::transaction(function () use ($point, $actor): void {
            $point = AdditionalPoint::query()->whereKey($point->id)->lockForUpdate()->firstOrFail();
            $student = Student::query()->whereKey($point->student_id)->lockForUpdate()->firstOrFail();
            $oldTransaction = $this->currentTransaction($point);
            $oldValues = $point->only(['student_id', 'points', 'awarded_date', 'reason']);
            $this->reverse($student, $point, $oldTransaction, $actor, 'Koreksi penghapusan tambahan poin #'.$point->id.'.');
            $this->activityLogs->record($actor, 'delete', 'additional_point', $point, $oldValues);
            $point->delete();
        });
    }

    private function currentTransaction(AdditionalPoint $point): PointTransaction
    {
        if ($point->point_transaction_id === null) {
            throw ValidationException::withMessages([
                'additional_point' => 'Transaksi ledger tidak ditemukan; perubahan tidak dapat diterapkan dengan aman.',
            ]);
        }

        return PointTransaction::query()->whereKey($point->point_transaction_id)->lockForUpdate()->firstOrFail();
    }

    private function reverse(Student $student, AdditionalPoint $point, PointTransaction $transaction, User $actor, string $description): void
    {
        $this->transactions->record(
            $student,
            $this->category(),
            $actor,
            'additional_point',
            $point->id,
            -$transaction->points,
            $description,
            today()->toDateString(),
            $transaction->id,
        );
    }

    private function category(): ViolationCategory
    {
        return ViolationCategory::query()->firstOrCreate(
            ['name' => 'Poin Tambahan', 'type' => 'reward'],
            ['points' => 1, 'description' => 'Poin tambahan yang diberikan oleh guru.'],
        );
    }
}
