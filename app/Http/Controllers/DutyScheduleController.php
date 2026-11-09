<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ConfirmDutyScheduleRequest;
use App\Http\Requests\StoreDutySchedulePreviewRequest;
use App\Models\DutySchedule;
use App\Models\User;
use App\Services\DutyScheduleService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class DutyScheduleController extends Controller
{
    public function index(Request $request, DutyScheduleService $service): View
    {
        $filters = $request->validate([
            'period' => ['nullable', 'in:week,month'],
            'start_date' => ['nullable', 'date_format:Y-m-d'],
        ]);
        $period = $filters['period'] ?? 'week';
        $startDate = isset($filters['start_date'])
            ? Carbon::parse($filters['start_date'], 'Asia/Jakarta')
            : now('Asia/Jakarta');
        [$start, $end] = $this->periodBounds($period, $startDate);

        $schedules = DutySchedule::query()
            ->whereBetween('duty_date', [$start->toDateString(), $end->toDateString()])
            ->orderBy('duty_date')
            ->get();
        $replacementSchedules = DutySchedule::query()
            ->where('duty_date', '>=', today('Asia/Jakarta')->toDateString())
            ->where('status', 'scheduled')
            ->orderBy('duty_date')
            ->limit(30)
            ->get();

        $service->ensureMemberStudentLinks($schedules->merge($replacementSchedules));
        $schedules->load(['members.role', 'replacements.originalUser', 'replacements.replacementUser']);
        $replacementSchedules->load('members');

        return view('duty-schedules.index', [
            'period' => $period,
            'startDate' => $start,
            'endDate' => $end,
            'schedules' => $schedules,
            'operationalUsers' => $this->activeOperationalUsers(),
            'replacementSchedules' => $replacementSchedules,
            'isScheduleManager' => $request->user()->canManageSchedule(),
        ]);
    }

    public function preview(
        StoreDutySchedulePreviewRequest $request,
        DutyScheduleService $service,
    ): View {
        [$start, $end] = $this->periodBounds(
            $request->validated('period'),
            Carbon::parse($request->validated('start_date'), 'Asia/Jakarta'),
        );
        $schedule = $service->generate($start, $end);

        return view('duty-schedules.preview', [
            'period' => $request->validated('period'),
            'startDate' => $start,
            'endDate' => $end,
            'schedule' => $schedule,
            'operationalUsers' => $this->activeOperationalUsers(),
        ]);
    }

    public function previewFallback(): RedirectResponse
    {
        return to_route('duty-schedules.index');
    }

    public function confirm(
        ConfirmDutyScheduleRequest $request,
        DutyScheduleService $service,
    ): RedirectResponse {
        [$start, $end] = $this->periodBounds(
            $request->validated('period'),
            Carbon::parse($request->validated('start_date'), 'Asia/Jakarta'),
        );
        $schedule = $service->validateSchedule($request->validated('schedule'), $start, $end);
        $service->confirm($schedule, $request->user());

        return to_route('duty-schedules.index', [
            'period' => $request->validated('period'),
            'start_date' => $start->toDateString(),
        ])->with('status', 'Jadwal piket berhasil dikonfirmasi dan disimpan.');
    }

    /**
     * @return array{Carbon, Carbon}
     */
    private function periodBounds(string $period, Carbon $date): array
    {
        if ($period === 'month') {
            return [$date->copy()->startOfMonth(), $date->copy()->endOfMonth()];
        }

        return [$date->copy()->startOfWeek(), $date->copy()->startOfWeek()->addDays(4)];
    }

    /**
     * @return Collection<int, User>
     */
    private function activeOperationalUsers()
    {
        return User::query()
            ->whereHas('role', fn ($query) => $query->where('name', 'operasional'))
            ->where('is_active', true)
            ->orderBy('username')
            ->get();
    }
}
