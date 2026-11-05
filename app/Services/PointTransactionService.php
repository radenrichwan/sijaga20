<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\PointTransaction;
use App\Models\Student;
use App\Models\User;
use App\Models\ViolationCategory;
use Illuminate\Support\Facades\DB;

class PointTransactionService
{
    public function record(
        Student $student,
        ViolationCategory $category,
        User $actor,
        ?string $sourceType,
        ?int $sourceId,
        int $points,
        string $description,
        string $transactionDate,
        ?int $counterEntryFor = null,
    ): PointTransaction {
        return DB::transaction(function () use (
            $student,
            $category,
            $actor,
            $sourceType,
            $sourceId,
            $points,
            $description,
            $transactionDate,
            $counterEntryFor,
        ): PointTransaction {
            $lockedStudent = Student::query()->whereKey($student->id)->lockForUpdate()->firstOrFail();
            $transaction = PointTransaction::query()->create([
                'student_id' => $lockedStudent->id,
                'category_id' => $category->id,
                'recorded_by' => $actor->id,
                'type' => $points < 0 ? 'violation' : 'reward',
                'points' => $points,
                'description' => $description,
                'transaction_date' => $transactionDate,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'counter_entry_for' => $counterEntryFor,
                'is_active' => true,
            ]);

            $lockedStudent->current_points = $lockedStudent->initial_points
                + (int) PointTransaction::query()
                    ->where('student_id', $lockedStudent->id)
                    ->where('is_active', true)
                    ->sum('points');
            $lockedStudent->save();

            return $transaction
                ->setRelation('student', $lockedStudent)
                ->setRelation('category', $category)
                ->setRelation('recordedBy', $actor);
        });
    }
}
