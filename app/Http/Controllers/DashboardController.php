<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\DutyAttendance;
use App\Models\DutySchedule;
use App\Models\LatenessRecord;
use App\Models\MorningReward;
use App\Models\PointTransaction;
use App\Models\Student;
use App\Models\Violation;
use App\Models\ViolationCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless($user->isActive(), 403);

        if ($user->isSiswa()) {
            $student = $user->student()->withCalculatedPoints()->first();
            abort_if($student === null, 403, 'Akun siswa belum terhubung ke profil siswa.');

            $transactions = $student->pointTransactions()
                ->with('category')
                ->where('is_active', true)
                ->latest('transaction_date')
                ->latest('id');
            $recentTransactions = (clone $transactions)->limit(12)->get();
            $chartStart = today()->subDays(29);
            $balance = $student->initial_points + $student->pointTransactions()
                ->where('is_active', true)
                ->whereDate('transaction_date', '<', $chartStart)
                ->sum('points');
            $dailyTotals = $student->pointTransactions()
                ->where('is_active', true)
                ->whereDate('transaction_date', '>=', $chartStart)
                ->selectRaw('transaction_date, SUM(points) as daily_points')
                ->groupBy('transaction_date')
                ->orderBy('transaction_date')
                ->get()
                ->keyBy(fn (PointTransaction $transaction): string => $transaction->transaction_date->toDateString());
            $labels = [];
            $pointTrend = [];
            $cursor = $chartStart->copy();

            while ($cursor->lte(today())) {
                $key = $cursor->toDateString();
                $labels[] = $cursor->format('d/m');
                $balance += (int) ($dailyTotals->get($key)?->daily_points ?? 0);
                $pointTrend[] = $balance;
                $cursor->addDay();
            }

            $transactionsByType = $recentTransactions->groupBy('type');

            return view('dashboard', [
                'dashboardRole' => 'siswa',
                'student' => $student->load('user'),
                'objections' => $student->objections()
                    ->with(['source.category', 'decidedBy', 'correctionTransaction'])
                    ->latest()
                    ->limit(5)
                    ->get(),
                'recentTransactions' => $recentTransactions,
                'violationHistory' => $transactionsByType->get('violation', collect())->take(5),
                'latenessHistory' => $student->latenessRecords()->latest('attendance_date')->limit(5)->get(),
                'rewardHistory' => $transactionsByType->get('reward', collect())->take(5),
                'attendanceHistory' => $student->dutyAttendances()->with('schedule')->latest('attended_at')->limit(5)->get(),
                'pointTrendLabels' => $labels,
                'pointTrend' => $pointTrend,
                'pointChartConfig' => [
                    'type' => 'line',
                    'data' => [
                        'labels' => $labels,
                        'datasets' => [[
                            'label' => 'Saldo poin',
                            'data' => $pointTrend,
                            'borderColor' => '#2563eb',
                            'backgroundColor' => 'rgba(37, 99, 235, .12)',
                            'fill' => true,
                            'tension' => .3,
                        ]],
                    ],
                    'options' => [
                        'responsive' => true,
                        'maintainAspectRatio' => false,
                        'plugins' => ['legend' => ['display' => false]],
                    ],
                ],
            ]);
        }

        if ($user->isOperasional()) {
            $today = Carbon::today('Asia/Jakarta');
            $schedule = DutySchedule::query()
                ->whereDate('duty_date', today())
                ->where('status', 'scheduled')
                ->with(['members', 'attendances.member', 'attendances.student.user', 'replacements.originalUser', 'replacements.replacementUser', 'replacements.replacementStudent.user'])
                ->first();
            $students = Student::query()
                ->with('user')
                ->orderBy('class_name')
                ->orderBy('nis')
                ->get();

            return view('dashboard', [
                'dashboardRole' => 'operasional',
                'studentCount' => Student::query()->where('is_active', true)->count(),
                'todayViolationCount' => Violation::query()->whereDate('violation_date', $today->toDateString())->count(),
                'todayLatenessCount' => LatenessRecord::query()->whereDate('attendance_date', $today->toDateString())->count(),
                'todayAttendanceCount' => DutyAttendance::query()->whereBetween('attended_at', [
                    $today->copy()->startOfDay(),
                    $today->copy()->endOfDay(),
                ])->count(),
                'schedule' => $schedule,
                'scheduleStudents' => Student::query()
                    ->with('user')
                    ->whereIn('id', $schedule?->members->pluck('pivot.student_id')->filter()->all() ?? [])
                    ->get()
                    ->keyBy('id'),
                'students' => $students,
                'usedRewardRanks' => MorningReward::query()
                    ->whereDate('reward_date', today())
                    ->pluck('rank')
                    ->all(),
                'todayRewards' => MorningReward::query()
                    ->with('student.user', 'recordedBy')
                    ->whereDate('reward_date', today())
                    ->orderBy('rank')
                    ->get(),
            ]);
        }

        $validated = $request->validate([
            'start_date' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
        ]);
        $startDate = isset($validated['start_date'])
            ? Carbon::parse($validated['start_date'])->startOfDay()
            : (isset($validated['end_date'])
                ? Carbon::parse($validated['end_date'])->startOfMonth()
                : today()->startOfMonth());
        $endDate = isset($validated['end_date'])
            ? Carbon::parse($validated['end_date'])->endOfDay()
            : today()->endOfDay();
        $today = Carbon::today('Asia/Jakarta');
        if ($endDate->lt($startDate)) {
            $request->validate([
                'end_date' => ['after_or_equal:'.$startDate->toDateString()],
            ]);
        }
        $transactions = PointTransaction::query()
            ->where('is_active', true)
            ->whereDate('transaction_date', '>=', $startDate->toDateString())
            ->whereDate('transaction_date', '<=', $endDate->toDateString());

        $topCategories = ViolationCategory::query()
            ->withCount(['violations' => function (Builder $query) use ($startDate, $endDate): void {
                $query->whereDate('violation_date', '>=', $startDate->toDateString())
                    ->whereDate('violation_date', '<=', $endDate->toDateString());
            }])
            ->where('type', 'violation')
            ->orderByDesc('violations_count')
            ->limit(5)
            ->get();

        $activeStudents = Student::query()
            ->withCalculatedPoints()
            ->where('is_active', true)
            ->with('user')
            ->orderBy('class_name')
            ->orderBy('nis')
            ->get();
        $allStudents = Student::query()
            ->with('user')
            ->orderBy('class_name')
            ->orderBy('nis')
            ->get();

        return view('dashboard', [
            'dashboardRole' => 'guru',
            'startDate' => $startDate->toDateString(),
            'endDate' => $endDate->toDateString(),
            'studentCount' => Student::query()->where('is_active', true)->count(),
            'violationCount' => Violation::query()->whereDate('violation_date', $today->toDateString())->count(),
            'latenessCount' => LatenessRecord::query()->whereDate('attendance_date', $today->toDateString())->count(),
            'morningRewardCount' => MorningReward::query()->whereDate('reward_date', $today->toDateString())->count(),
            'attendanceCount' => DutyAttendance::query()->whereBetween('attended_at', [
                $today->copy()->startOfDay(),
                $today->copy()->endOfDay(),
            ])->count(),
            'topCategories' => $topCategories,
            'categoryChartConfig' => [
                'type' => 'bar',
                'data' => [
                    'labels' => $topCategories->pluck('name')->values(),
                    'datasets' => [[
                        'label' => 'Jumlah pelanggaran',
                        'data' => $topCategories->pluck('violations_count')->values(),
                        'backgroundColor' => '#2563eb',
                        'borderRadius' => 6,
                    ]],
                ],
                'options' => [
                    'responsive' => true,
                    'maintainAspectRatio' => false,
                    'plugins' => ['legend' => ['display' => false]],
                    'scales' => ['y' => ['beginAtZero' => true, 'ticks' => ['precision' => 0]]],
                ],
            ],
            'totalPoints' => (int) $activeStudents->sum('current_points'),
            'startDateInput' => $validated['start_date'] ?? $startDate->toDateString(),
            'endDateInput' => $validated['end_date'] ?? $endDate->toDateString(),
            'transactionCount' => (clone $transactions)->count(),
            'students' => $allStudents,
            'usedRewardRanks' => MorningReward::query()
                ->whereDate('reward_date', today())
                ->pluck('rank')
                ->all(),
            'todayRewards' => MorningReward::query()
                ->with(['student.user', 'recordedBy'])
                ->whereDate('reward_date', today())
                ->orderBy('rank')
                ->get(),
        ]);
    }
}
