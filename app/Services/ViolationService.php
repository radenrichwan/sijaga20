<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\PointTransaction;
use App\Models\Student;
use App\Models\User;
use App\Models\Violation;
use App\Models\ViolationCategory;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ViolationService
{
    public function __construct(
        private readonly PointTransactionService $transactions,
        private readonly ActivityLogService $activityLogs,
        private readonly InternalNotificationService $notifications,
    ) {}

    public function create(array $data, User $actor): Violation
    {
        return DB::transaction(function () use ($data, $actor): Violation {
            $student = Student::query()
                ->where('is_active', true)
                ->where('nis', $data['student_nis'])
                ->lockForUpdate()
                ->firstOrFail();
            $categoryIds = $data['violation_category_ids'] ?? [$data['violation_category_id']];
            $categories = collect($categoryIds)->map(fn (int|string $id): ViolationCategory => $this->category($id));
            $categoryNames = $categories->mapWithKeys(
                fn (ViolationCategory $category): array => [$category->id => $category->name],
            );
            $categoryCounts = collect($categoryIds)->countBy();
            $summary = $categoryCounts->map(function (int $count, int|string $id) use ($categoryNames): string {
                $name = $categoryNames->get((int) $id);
                if (! is_string($name)) {
                    throw ValidationException::withMessages([
                        'violation_category_ids' => 'Kategori pelanggaran tidak ditemukan.',
                    ]);
                }

                return $name.' '.$count.'X';
            })
                ->values()
                ->implode(', ');
            $transactionDescription = trim($summary.(! empty($data['description']) ? ': '.$data['description'] : ''));
            $createdViolations = collect();

            foreach ($categories as $category) {
                $violation = Violation::query()->create([
                    'student_id' => $student->id,
                    'violation_category_id' => $category->id,
                    'recorded_by' => $actor->id,
                    'violation_date' => $data['violation_date'],
                    'description' => $data['description'] ?? null,
                ]);
                $transaction = $this->transactions->record(
                    $student,
                    $category,
                    $actor,
                    'violation',
                    $violation->id,
                    -5,
                    $transactionDescription,
                    $violation->violation_date->toDateString(),
                );
                $violation->update(['point_transaction_id' => $transaction->id]);
                $this->activityLogs->record($actor, 'create', 'violation', $violation, null, [
                    'student_id' => $student->id,
                    'category_id' => $category->id,
                    'violation_date' => $violation->violation_date->toDateString(),
                    'points' => -5,
                ]);
                $this->notifications->send(
                    [$student->user],
                    'violation.created',
                    'Catatan pelanggaran baru',
                    'Pelanggaran '.$category->name.' dicatat dan saldo poin Anda berkurang 5.',
                    route('objections.create'),
                    ['violation_id' => $violation->id, 'points' => -5],
                );
                $createdViolations->push($violation);
            }

            return $createdViolations->first();
        });
    }

    public function update(Violation $violation, array $data, User $actor): void
    {
        DB::transaction(function () use ($violation, $data, $actor): void {
            $student = Student::query()->whereKey($violation->student_id)->lockForUpdate()->firstOrFail();
            $violation = Violation::query()->whereKey($violation->id)->lockForUpdate()->firstOrFail();
            $oldTransaction = $this->currentTransaction($violation->point_transaction_id);
            $oldValues = $violation->only(['student_id', 'violation_category_id', 'violation_date', 'description']);
            $category = $this->category($data['violation_category_id']);
            $this->reverse($student, $oldTransaction, $actor, 'Koreksi perubahan pelanggaran #'.$violation->id.'.');
            $violation->fill([
                'violation_category_id' => $category->id,
                'violation_date' => $data['violation_date'],
                'description' => $data['description'] ?? null,
            ])->save();
            $transaction = $this->transactions->record(
                $student,
                $category,
                $actor,
                'violation',
                $violation->id,
                -5,
                $this->description($category, $data['description'] ?? null),
                $violation->violation_date->toDateString(),
                $oldTransaction->id,
            );
            $violation->update(['point_transaction_id' => $transaction->id]);
            $this->activityLogs->record($actor, 'update', 'violation', $violation, $oldValues, [
                'student_id' => $student->id,
                'category_id' => $category->id,
                'violation_date' => $violation->violation_date->toDateString(),
                'description' => $violation->description,
                'points' => -5,
            ]);
        });
    }

    public function delete(Violation $violation, User $actor): void
    {
        DB::transaction(function () use ($violation, $actor): void {
            $student = Student::query()->whereKey($violation->student_id)->lockForUpdate()->firstOrFail();
            $violation = Violation::query()->whereKey($violation->id)->lockForUpdate()->firstOrFail();
            $oldTransaction = $this->currentTransaction($violation->point_transaction_id);
            $oldValues = $violation->only(['student_id', 'violation_category_id', 'violation_date', 'description']);
            $this->reverse($student, $oldTransaction, $actor, 'Koreksi penghapusan pelanggaran #'.$violation->id.'.');
            $this->activityLogs->record($actor, 'delete', 'violation', $violation, $oldValues);
            $violation->delete();
        });
    }

    private function category(int|string $categoryId): ViolationCategory
    {
        return ViolationCategory::query()
            ->whereKey($categoryId)
            ->where('type', 'violation')
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function currentTransaction(?int $transactionId): PointTransaction
    {
        if ($transactionId === null) {
            throw ValidationException::withMessages([
                'violation' => 'Transaksi poin terkait tidak ditemukan; perubahan tidak dapat diterapkan dengan aman.',
            ]);
        }

        return PointTransaction::query()->whereKey($transactionId)->lockForUpdate()->firstOrFail();
    }

    private function reverse(Student $student, PointTransaction $transaction, User $actor, string $description): void
    {
        $this->transactions->record(
            $student,
            $transaction->category()->firstOrFail(),
            $actor,
            'violation',
            (int) $transaction->source_id,
            -$transaction->points,
            $description,
            today()->toDateString(),
            $transaction->id,
        );
    }

    private function description(ViolationCategory $category, ?string $description): string
    {
        return trim($category->name.($description ? ': '.$description : ''));
    }
}
