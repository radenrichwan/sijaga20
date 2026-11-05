<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreLatenessRequest;
use App\Http\Requests\UpdateLatenessRequest;
use App\Models\LatenessRecord;
use App\Models\Student;
use App\Services\LatenessService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class LatenessController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'month' => ['nullable', 'date_format:Y-m'],
            'class_name' => ['nullable', 'string', 'max:50'],
        ]);
        if (! isset($filters['start_date']) && ! isset($filters['end_date'])) {
            $filters['month'] ??= now('Asia/Jakarta')->format('Y-m');
        }

        $records = LatenessRecord::query()
            ->with(['student.user', 'recordedBy', 'pointTransaction'])
            ->when(($filters['search'] ?? '') !== '', function (Builder $query) use ($filters): void {
                $search = trim($filters['search']);
                $query->whereHas('student', function (Builder $students) use ($search): void {
                    $students->where('nis', 'like', "%{$search}%")
                        ->orWhere('nisn', 'like', "%{$search}%")
                        ->orWhereHas('user', fn (Builder $users) => $users->where('name', 'like', "%{$search}%"));
                });
            })
            ->when(isset($filters['start_date']), fn (Builder $query) => $query->whereDate('attendance_date', '>=', $filters['start_date']))
            ->when(isset($filters['end_date']), fn (Builder $query) => $query->whereDate('attendance_date', '<=', $filters['end_date']))
            ->when(isset($filters['month']) && $filters['month'] !== '', function (Builder $query) use ($filters): void {
                $month = Carbon::createFromFormat('Y-m', $filters['month'], 'Asia/Jakarta');
                $query->whereYear('attendance_date', $month->year)->whereMonth('attendance_date', $month->month);
            })
            ->when(isset($filters['class_name']) && $filters['class_name'] !== '', fn (Builder $query) => $query->whereHas('student', fn (Builder $students) => $students->where('class_name', $filters['class_name'])))
            ->orderByDesc('attendance_date')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('lateness.index', [
            'records' => $records,
            'filters' => $filters,
            'classes' => Student::query()->whereNotNull('class_name')->distinct()->orderBy('class_name')->pluck('class_name'),
        ]);
    }

    public function create(): View
    {
        return view('lateness.create', [
            'students' => Student::query()->with('user')->orderBy('class_name')->orderBy('nis')->get(),
        ]);
    }

    public function store(StoreLatenessRequest $request, LatenessService $service): RedirectResponse
    {
        $service->create($request->validated(), $request->user());

        return to_route('lateness.index')->with('status', 'Keterlambatan dicatat dan saldo siswa dikurangi 5 poin.');
    }

    public function edit(LatenessRecord $lateness): View
    {
        $lateness->load(['student.user', 'pointTransaction']);

        return view('lateness.edit', ['record' => $lateness]);
    }

    public function update(
        UpdateLatenessRequest $request,
        LatenessRecord $lateness,
        LatenessService $service,
    ): RedirectResponse {
        $service->update($lateness, $request->validated(), $request->user());

        return to_route('lateness.index')->with('status', 'Keterlambatan diperbarui dengan transaksi koreksi ledger.');
    }

    public function destroy(LatenessRecord $lateness, LatenessService $service): RedirectResponse
    {
        $service->delete($lateness, request()->user());

        return to_route('lateness.index')->with('status', 'Keterlambatan dihapus dari daftar dengan transaksi koreksi ledger.');
    }
}
