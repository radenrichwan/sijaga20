<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreMorningRewardRequest;
use App\Http\Requests\UpdateMorningRewardRequest;
use App\Models\MorningReward;
use App\Models\Student;
use App\Services\MorningRewardService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class MorningRewardController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'class_name' => ['nullable', 'string', 'max:50'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);
        if (! isset($filters['start_date']) && ! isset($filters['end_date'])) {
            $filters['month'] ??= now('Asia/Jakarta')->format('Y-m');
        }

        return view('morning-rewards.index', [
            'filters' => $filters,
            'rewards' => MorningReward::query()
                ->with(['student.user', 'recordedBy', 'pointTransaction'])
                ->when(isset($filters['start_date']), fn (Builder $query) => $query->whereDate('reward_date', '>=', $filters['start_date']))
                ->when(isset($filters['end_date']), fn (Builder $query) => $query->whereDate('reward_date', '<=', $filters['end_date']))
                ->when(isset($filters['month']) && $filters['month'] !== '', function (Builder $query) use ($filters): void {
                    $month = Carbon::createFromFormat('Y-m', $filters['month'], 'Asia/Jakarta');
                    $query->whereYear('reward_date', $month->year)->whereMonth('reward_date', $month->month);
                })
                ->when(($filters['class_name'] ?? '') !== '', fn (Builder $query) => $query->whereHas('student', fn (Builder $students) => $students->where('class_name', $filters['class_name'])))
                ->when(($filters['search'] ?? '') !== '', function (Builder $query) use ($filters): void {
                    $search = trim($filters['search']);
                    $query->whereHas('student', function (Builder $students) use ($search): void {
                        $students->where('nis', 'like', "%{$search}%")
                            ->orWhere('nisn', 'like', "%{$search}%")
                            ->orWhereHas('user', fn (Builder $users) => $users->where('name', 'like', "%{$search}%"));
                    });
                })
                ->orderByDesc('reward_date')
                ->orderBy('rank')
                ->paginate(20)
                ->withQueryString(),
            'classes' => Student::query()->distinct()->orderBy('class_name')->pluck('class_name'),
        ]);
    }

    public function store(StoreMorningRewardRequest $request, MorningRewardService $service): RedirectResponse
    {
        $service->create($request->validated(), $request->user());

        return to_route('dashboard')
            ->with('status', 'Penghargaan pagi dan poin +15 berhasil dicatat.');
    }

    public function edit(MorningReward $morning_reward): View
    {
        $morning_reward->load('student.user');

        return view('morning-rewards.edit', [
            'reward' => $morning_reward,
            'students' => Student::query()->with('user')->orderBy('class_name')->orderBy('nis')->get(),
        ]);
    }

    public function update(
        UpdateMorningRewardRequest $request,
        MorningReward $morning_reward,
        MorningRewardService $service,
    ): RedirectResponse {
        $service->update($morning_reward, $request->validated(), $request->user());

        return to_route('morning-rewards.index')->with('status', 'Penghargaan pagi diperbarui dengan transaksi koreksi ledger.');
    }

    public function destroy(MorningReward $morning_reward, MorningRewardService $service): RedirectResponse
    {
        $service->delete($morning_reward, request()->user());

        return to_route('morning-rewards.index')->with('status', 'Penghargaan pagi dihapus dengan transaksi koreksi ledger.');
    }
}
