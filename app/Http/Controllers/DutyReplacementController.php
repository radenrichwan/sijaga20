<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\DecideDutyReplacementRequest;
use App\Http\Requests\StoreDutyReplacementRequest;
use App\Models\DutyReplacement;
use App\Models\DutySchedule;
use App\Models\Student;
use App\Models\User;
use App\Services\DutyReplacementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DutyReplacementController extends Controller
{
    public function index(Request $request): View
    {
        return view('duty-replacements.index', [
            'replacements' => DutyReplacement::query()
                ->with(['schedule', 'originalUser', 'replacementUser', 'replacementStudent.user', 'requestedBy', 'decidedBy'])
                ->latest()
                ->paginate(20),
            'schedules' => DutySchedule::query()
                ->with('members')
                ->where('duty_date', '>=', today('Asia/Jakarta')->toDateString())
                ->where('status', 'scheduled')
                ->orderBy('duty_date')
                ->limit(30)
                ->get(),
            'operationalUsers' => User::query()
                ->whereHas('role', fn ($query) => $query->where('name', 'operasional'))
                ->where('is_active', true)
                ->orderBy('username')
                ->get(),
            'activeStudents' => Student::query()
                ->where('is_active', true)
                ->with('user')
                ->orderBy('nis')
                ->get(),
            'isScheduleManager' => $request->user()->canManageSchedule(),
        ]);
    }

    public function store(
        StoreDutyReplacementRequest $request,
        DutyReplacementService $service,
    ): RedirectResponse {
        $service->request($request->validated(), $request->user());

        return to_route('duty-replacements.index')
            ->with('status', 'Pengajuan penggantian anggota tersimpan dan menunggu persetujuan.');
    }

    public function decide(
        DecideDutyReplacementRequest $request,
        DutyReplacement $duty_replacement,
        DutyReplacementService $service,
    ): RedirectResponse {
        $status = $request->validated('status');
        $service->decide(
            $duty_replacement,
            $request->user(),
            $status,
            $request->validated('decision_reason'),
        );

        return to_route('duty-replacements.index')
            ->with('status', $status === 'approved' ? 'Penggantian anggota disetujui.' : 'Pengajuan penggantian ditolak.');
    }
}
