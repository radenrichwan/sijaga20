<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreDutyAttendanceRequest;
use App\Models\DutyAttendance;
use App\Models\DutySchedule;
use App\Models\Student;
use App\Models\User;
use App\Services\DutyAttendanceService;
use App\Services\DutyScheduleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class DutyAttendanceController extends Controller
{
    public function index(Request $request, DutyScheduleService $scheduleService): View
    {
        $filters = $request->validate([
            'period' => ['nullable', 'in:day,month,year'],
            'date' => ['nullable', 'date_format:Y-m-d'],
        ]);
        $period = $filters['period'] ?? 'day';
        $date = isset($filters['date'])
            ? Carbon::parse($filters['date'], 'Asia/Jakarta')
            : now('Asia/Jakarta');
        [$start, $end] = match ($period) {
            'month' => [$date->copy()->startOfMonth(), $date->copy()->endOfMonth()],
            'year' => [$date->copy()->startOfYear(), $date->copy()->endOfYear()],
            default => [$date->copy()->startOfDay(), $date->copy()->endOfDay()],
        };

        $schedule = DutySchedule::query()
            ->whereDate('duty_date', today('Asia/Jakarta')->toDateString())
            ->where('status', 'scheduled')
            ->first();

        if ($schedule !== null) {
            $scheduleService->ensureMemberStudentLinks(collect([$schedule]));
            $schedule->load([
                'members',
                'attendances.member',
                'attendances.student.user',
                'replacements.originalUser',
                'replacements.replacementUser',
                'replacements.replacementStudent.user',
            ]);
        }

        return view('duty-attendances.index', [
            'filters' => ['period' => $period, 'date' => $date->toDateString()],
            'start' => $start,
            'end' => $end,
            'schedule' => $schedule,
            'eligibleMembers' => $schedule ? $this->eligibleMembers($schedule) : collect(),
            'attendances' => DutyAttendance::query()
                ->with(['schedule', 'member', 'student.user', 'recordedBy'])
                ->whereBetween('attended_at', [$start, $end])
                ->orderByDesc('attended_at')
                ->paginate(25)
                ->withQueryString(),
            'attendanceCount' => DutyAttendance::query()->whereBetween('attended_at', [$start, $end])->count(),
            'pointsAwarded' => DutyAttendance::query()
                ->whereBetween('attended_at', [$start, $end])
                ->whereHas('pointTransaction', fn ($query) => $query->where('is_active', true))
                ->count() * 10,
            'canRecord' => $request->user()->isOperasional(),
        ]);
    }

    public function store(
        StoreDutyAttendanceRequest $request,
        DutyAttendanceService $service,
    ): RedirectResponse {
        $schedule = DutySchedule::query()->findOrFail($request->validated('duty_schedule_id'));
        $member = User::query()->findOrFail($request->validated('member_user_id'));
        $service->record($schedule, $member, $request->user());

        return to_route('duty-attendances.index')
            ->with('status', 'Kehadiran berhasil dicatat dan poin +10 diberikan secara otomatis.');
    }

    /**
     * @return Collection<int, array{user: User, student: Student, replacement: bool, attended: bool}>
     */
    private function eligibleMembers(DutySchedule $schedule)
    {
        $approvedReplacements = $schedule->replacements
            ->where('status', 'approved')
            ->keyBy('original_user_id');
        $attendedIds = $schedule->attendances->pluck('member_user_id')->filter()->all();
        $members = collect();

        foreach ($schedule->members as $member) {
            if ($approvedReplacements->has($member->id)) {
                continue;
            }

            $student = Student::query()
                ->whereKey($member->pivot->student_id)
                ->where('is_active', true)
                ->with('user')
                ->first();
            if ($student !== null) {
                $members->push([
                    'user' => $member,
                    'student' => $student,
                    'replacement' => false,
                    'attended' => in_array($member->id, $attendedIds, true),
                ]);
            }
        }

        foreach ($approvedReplacements as $replacement) {
            $student = $replacement->replacementStudent;
            $member = $replacement->replacementUser;
            if ($student?->is_active && $member?->is_active) {
                $members->push([
                    'user' => $member,
                    'student' => $student->loadMissing('user'),
                    'replacement' => true,
                    'attended' => in_array($member->id, $attendedIds, true),
                ]);
            }
        }

        return $members->values();
    }
}
