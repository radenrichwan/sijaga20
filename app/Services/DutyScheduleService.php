<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\DutySchedule;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DutyScheduleService
{
    public function __construct(
        private readonly ActivityLogService $activityLogs,
        private readonly InternalNotificationService $notifications,
    ) {}

    /**
     * @return array<string, list<int>>
     */
    public function generate(Carbon $startDate, Carbon $endDate): array
    {
        $members = User::query()
            ->whereHas('role', fn ($query) => $query->where('name', 'operasional'))
            ->where('is_active', true)
            ->get()
            ->shuffle();

        if ($members->count() < 4) {
            throw ValidationException::withMessages([
                'schedule' => 'Minimal empat akun operasional aktif diperlukan untuk membuat jadwal.',
            ]);
        }

        $dates = $this->weekdaysBetween($startDate, $endDate);
        $preview = [];

        foreach ($dates->groupBy(fn (Carbon $date): string => $date->copy()->startOfWeek()->toDateString()) as $weekDates) {
            $counts = $members->mapWithKeys(fn (User $member): array => [$member->id => 0])->all();

            foreach ($weekDates as $date) {
                $tieBreakers = $members->shuffle()->values()->mapWithKeys(
                    fn (User $member, int $index): array => [$member->id => $index],
                );
                $selected = $members
                    ->sort(function (User $left, User $right) use ($counts, $tieBreakers): int {
                        return [$counts[$left->id], $tieBreakers[$left->id]]
                            <=> [$counts[$right->id], $tieBreakers[$right->id]];
                    })
                    ->take(4)
                    ->pluck('id')
                    ->map(fn (int $id): int => $id)
                    ->values()
                    ->all();

                foreach ($selected as $memberId) {
                    $counts[$memberId]++;
                }

                $preview[$date->toDateString()] = $selected;
            }
        }

        return $preview;
    }

    /**
     * @param  array<string, mixed>  $schedule
     * @return Collection<string, Collection<int, int>>
     */
    public function validateSchedule(array $schedule, Carbon $startDate, Carbon $endDate): Collection
    {
        $expectedDates = $this->weekdaysBetween($startDate, $endDate)->map(
            fn (Carbon $date): string => $date->toDateString(),
        );
        $submittedDates = collect(array_keys($schedule))->sort()->values();

        if ($expectedDates->sort()->values()->all() !== $submittedDates->all()) {
            throw ValidationException::withMessages([
                'schedule' => 'Jadwal harus mencakup setiap hari kerja dalam periode dan tidak boleh memuat Sabtu atau Minggu.',
            ]);
        }

        $memberIds = collect($schedule)->flatten()->map(fn ($id): int => (int) $id);
        if ($memberIds->contains(0)) {
            throw ValidationException::withMessages([
                'schedule' => 'Pilihan anggota tidak valid.',
            ]);
        }

        $activeOperationalIds = User::query()
            ->whereHas('role', fn ($query) => $query->where('name', 'operasional'))
            ->where('is_active', true)
            ->pluck('id')
            ->map(fn (int $id): int => $id)
            ->all();

        if (! $memberIds->unique()->every(fn (int $id): bool => in_array($id, $activeOperationalIds, true))) {
            throw ValidationException::withMessages([
                'schedule' => 'Jadwal hanya dapat berisi akun operasional yang masih aktif.',
            ]);
        }

        $normalized = collect($schedule)->map(function ($members, string $date): Collection {
            if (! is_array($members)) {
                throw ValidationException::withMessages(['schedule' => 'Daftar anggota pada setiap hari tidak valid.']);
            }

            $ids = collect($members)->map(fn ($id): int => (int) $id);
            if ($ids->count() < 4 || $ids->count() > 6 || $ids->count() !== $ids->unique()->count()) {
                throw ValidationException::withMessages([
                    'schedule' => "Jadwal {$date} harus berisi 4–6 anggota yang berbeda.",
                ]);
            }

            return $ids->values();
        });

        foreach ($expectedDates->groupBy(fn (string $date): string => Carbon::parse($date)->startOfWeek()->toDateString()) as $weekDates) {
            $counts = collect($activeOperationalIds)->mapWithKeys(fn (int $id): array => [$id => 0]);
            foreach ($weekDates as $date) {
                foreach ($normalized->get($date) as $memberId) {
                    $counts->put($memberId, $counts->get($memberId, 0) + 1);
                }
            }
            if ($counts->max() - $counts->min() > 1) {
                throw ValidationException::withMessages([
                    'schedule' => 'Selisih jumlah hari bertugas setiap anggota dalam satu minggu maksimal satu hari.',
                ]);
            }
        }

        return $normalized;
    }

    /**
     * @param  Collection<string, Collection<int, int>>  $schedule
     */
    public function confirm(Collection $schedule, User $actor): void
    {
        DB::transaction(function () use ($schedule, $actor): void {
            $dates = $schedule->keys();
            $existingDates = DutySchedule::query()
                ->whereIn('duty_date', $dates)
                ->lockForUpdate()
                ->pluck('duty_date')
                ->map(fn ($date): string => Carbon::parse($date)->toDateString());

            if ($existingDates->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'schedule' => 'Jadwal untuk tanggal '.$existingDates->implode(', ').' sudah tersimpan.',
                ]);
            }

            foreach ($schedule as $date => $memberIds) {
                $monday = Carbon::parse($date)->startOfWeek()->toDateString();
                $dutySchedule = DutySchedule::query()->create([
                    'week_start' => $monday,
                    'duty_date' => $date,
                    'status' => 'scheduled',
                    'created_by' => $actor->id,
                ]);
                $dutySchedule->members()->attach($memberIds->all());
                $this->activityLogs->record($actor, 'create', 'duty_schedule', $dutySchedule, null, [
                    'duty_date' => $date,
                    'week_start' => $monday,
                    'member_user_ids' => $memberIds->all(),
                ]);
                $members = User::query()->whereIn('id', $memberIds)->get();
                $this->notifications->send(
                    $members,
                    'duty_schedule.released',
                    'Jadwal piket baru',
                    'Jadwal piket gerbang tanggal '.Carbon::parse($date)->translatedFormat('d F Y').' telah dirilis.',
                    route('duty-schedules.index', ['period' => 'week', 'start_date' => $date]),
                    ['duty_schedule_id' => $dutySchedule->id, 'duty_date' => $date],
                );
            }
        });
    }

    /**
     * @return Collection<int, Carbon>
     */
    private function weekdaysBetween(Carbon $startDate, Carbon $endDate): Collection
    {
        $dates = collect();
        $cursor = $startDate->copy()->startOfDay();
        while ($cursor->lte($endDate)) {
            if ($cursor->isWeekday()) {
                $dates->push($cursor->copy());
            }
            $cursor->addDay();
        }

        return $dates;
    }
}
