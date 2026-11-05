<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\MorningReward;
use App\Models\PointTransaction;
use App\Models\Student;
use App\Models\User;
use App\Models\ViolationCategory;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MorningRewardService
{
    public function __construct(
        private readonly PointTransactionService $transactions,
        private readonly ActivityLogService $activityLogs,
        private readonly InternalNotificationService $notifications,
    ) {}

    public function create(array $data, User $actor): MorningReward
    {
        return DB::transaction(function () use ($data, $actor): MorningReward {
            $student = Student::query()->whereKey($data['student_id'])->lockForUpdate()->firstOrFail();
            $this->assertSlotAvailable($student, $data['reward_date'], (int) $data['rank']);
            $reward = MorningReward::query()->create([
                'student_id' => $student->id,
                'recorded_by' => $actor->id,
                'reward_date' => $data['reward_date'],
                'arrival_time' => $data['arrival_time'],
                'rank' => $data['rank'],
            ]);
            $transaction = $this->transactions->record(
                $student,
                $this->category(),
                $actor,
                'morning_reward',
                $reward->id,
                15,
                sprintf('Penghargaan pagi peringkat %d.', $reward->rank),
                $reward->reward_date->toDateString(),
            );
            $reward->update(['point_transaction_id' => $transaction->id]);
            $this->activityLogs->record($actor, 'create', 'morning_reward', $reward, null, [
                'student_id' => $student->id,
                'reward_date' => $reward->reward_date->toDateString(),
                'rank' => $reward->rank,
                'points' => 15,
            ]);
            $this->notifications->send(
                [$student->user],
                'morning_reward.created',
                'Penghargaan pagi',
                'Anda menerima penghargaan pagi sebesar 15 poin.',
                route('students.show', $student),
                ['morning_reward_id' => $reward->id, 'points' => 15],
            );

            return $reward;
        });
    }

    public function update(MorningReward $reward, array $data, User $actor): void
    {
        DB::transaction(function () use ($reward, $data, $actor): void {
            $reward = MorningReward::query()->whereKey($reward->id)->lockForUpdate()->firstOrFail();
            $oldTransaction = $this->currentTransaction($reward);
            $studentIds = collect([$reward->student_id, (int) $data['student_id']])->unique()->sort()->values();
            $students = Student::query()->whereIn('id', $studentIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $oldStudent = $students->get($reward->student_id);
            $student = $students->get((int) $data['student_id']);
            $this->assertSlotAvailable($student, $data['reward_date'], (int) $data['rank'], $reward->id);
            $oldValues = $reward->only(['student_id', 'reward_date', 'arrival_time', 'rank']);

            $this->reverse($oldStudent, $oldTransaction, $actor, 'Koreksi perubahan penghargaan pagi #'.$reward->id.'.');
            $reward->fill([
                'student_id' => $student->id,
                'recorded_by' => $actor->id,
                'reward_date' => $data['reward_date'],
                'arrival_time' => $data['arrival_time'],
                'rank' => $data['rank'],
            ])->save();
            $transaction = $this->transactions->record(
                $student,
                $this->category(),
                $actor,
                'morning_reward',
                $reward->id,
                15,
                sprintf('Penghargaan pagi peringkat %d.', $reward->rank),
                $reward->reward_date->toDateString(),
                $oldTransaction->id,
            );
            $reward->update(['point_transaction_id' => $transaction->id]);
            $this->activityLogs->record($actor, 'update', 'morning_reward', $reward, $oldValues, [
                'student_id' => $student->id,
                'reward_date' => $reward->reward_date->toDateString(),
                'arrival_time' => $reward->arrival_time,
                'rank' => $reward->rank,
                'points' => 15,
            ]);
        });
    }

    public function delete(MorningReward $reward, User $actor): void
    {
        DB::transaction(function () use ($reward, $actor): void {
            $reward = MorningReward::query()->whereKey($reward->id)->lockForUpdate()->firstOrFail();
            $student = Student::query()->whereKey($reward->student_id)->lockForUpdate()->firstOrFail();
            $oldTransaction = $this->currentTransaction($reward);
            $oldValues = $reward->only(['student_id', 'reward_date', 'arrival_time', 'rank']);
            $this->reverse($student, $oldTransaction, $actor, 'Koreksi penghapusan penghargaan pagi #'.$reward->id.'.');
            $this->activityLogs->record($actor, 'delete', 'morning_reward', $reward, $oldValues);
            $reward->delete();
        });
    }

    private function assertSlotAvailable(Student $student, string $date, int $rank, ?int $ignoreId = null): void
    {
        $duplicateStudent = MorningReward::query()
            ->whereDate('reward_date', $date)
            ->where('student_id', $student->id)
            ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists();
        $duplicateRank = MorningReward::query()
            ->whereDate('reward_date', $date)
            ->where('rank', $rank)
            ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists();

        if ($duplicateStudent || $duplicateRank) {
            throw ValidationException::withMessages([
                $duplicateStudent ? 'student_id' : 'rank' => $duplicateStudent
                    ? 'Siswa hanya dapat menerima satu penghargaan pagi pada tanggal yang sama.'
                    : 'Peringkat tersebut sudah digunakan pada tanggal yang sama.',
            ]);
        }
    }

    private function category(): ViolationCategory
    {
        return ViolationCategory::query()
            ->where('type', 'reward')
            ->where('points', 15)
            ->orderBy('id')
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function currentTransaction(MorningReward $reward): PointTransaction
    {
        if ($reward->point_transaction_id === null) {
            throw ValidationException::withMessages([
                'morning_reward' => 'Transaksi ledger penghargaan tidak ditemukan; perubahan tidak dapat diterapkan dengan aman.',
            ]);
        }

        return PointTransaction::query()->whereKey($reward->point_transaction_id)->lockForUpdate()->firstOrFail();
    }

    private function reverse(Student $student, PointTransaction $transaction, User $actor, string $description): void
    {
        $this->transactions->record(
            $student,
            $this->category(),
            $actor,
            'morning_reward',
            (int) $transaction->source_id,
            -$transaction->points,
            $description,
            today()->toDateString(),
            $transaction->id,
        );
    }
}
