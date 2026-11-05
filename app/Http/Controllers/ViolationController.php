<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreViolationRequest;
use App\Http\Requests\UpdateViolationRequest;
use App\Models\Student;
use App\Models\Violation;
use App\Models\ViolationCategory;
use App\Services\ViolationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class ViolationController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'month' => ['nullable', 'date_format:Y-m'],
            'class_name' => ['nullable', 'string', 'max:50'],
            'violation_category_id' => ['nullable', 'integer', 'exists:violation_categories,id'],
        ]);
        if (! isset($filters['start_date']) && ! isset($filters['end_date'])) {
            $filters['month'] ??= now('Asia/Jakarta')->format('Y-m');
        }

        $violations = Violation::query()
            ->with(['student.user', 'category', 'recordedBy'])
            ->when(($filters['search'] ?? '') !== '', function (Builder $query) use ($filters): void {
                $search = trim($filters['search']);
                $query->whereHas('student', function (Builder $students) use ($search): void {
                    $students->where('nis', 'like', "%{$search}%")
                        ->orWhere('nisn', 'like', "%{$search}%")
                        ->orWhereHas('user', fn (Builder $users) => $users->where('name', 'like', "%{$search}%"));
                });
            })
            ->when(isset($filters['start_date']), fn (Builder $query) => $query->whereDate('violation_date', '>=', $filters['start_date']))
            ->when(isset($filters['end_date']), fn (Builder $query) => $query->whereDate('violation_date', '<=', $filters['end_date']))
            ->when(isset($filters['month']) && $filters['month'] !== '', function (Builder $query) use ($filters): void {
                $month = Carbon::createFromFormat('Y-m', $filters['month'], 'Asia/Jakarta');
                $query->whereYear('violation_date', $month->year)->whereMonth('violation_date', $month->month);
            })
            ->when(isset($filters['class_name']) && $filters['class_name'] !== '', fn (Builder $query) => $query->whereHas('student', fn (Builder $students) => $students->where('class_name', $filters['class_name'])))
            ->when(isset($filters['violation_category_id']) && $filters['violation_category_id'] !== '', fn (Builder $query) => $query->where('violation_category_id', $filters['violation_category_id']))
            ->orderByDesc('violation_date')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('violations.index', [
            'violations' => $violations,
            'filters' => $filters,
            'categories' => ViolationCategory::query()->where('type', 'violation')->orderBy('name')->get(),
            'classes' => Student::query()->whereNotNull('class_name')->distinct()->orderBy('class_name')->pluck('class_name'),
        ]);
    }

    public function create(): View
    {
        return view('violations.create', [
            'students' => Student::query()->with('user')->orderBy('class_name')->orderBy('nis')->get(),
            'categories' => ViolationCategory::query()->where('type', 'violation')->orderBy('name')->get(),
        ]);
    }

    public function store(StoreViolationRequest $request, ViolationService $service): RedirectResponse
    {
        $data = $request->validated();
        $service->create($data, $request->user());
        $count = count($data['violation_category_ids'] ?? [$data['violation_category_id']]);

        return to_route('violations.index')->with(
            'status',
            $count.' pelanggaran dicatat; saldo siswa dikurangi '.($count * 5).' poin.',
        );
    }

    public function edit(Violation $violation): View
    {
        $violation->load(['student.user', 'category']);

        return view('violations.edit', [
            'violation' => $violation,
            'categories' => ViolationCategory::query()->where('type', 'violation')->orderBy('name')->get(),
        ]);
    }

    public function update(
        UpdateViolationRequest $request,
        Violation $violation,
        ViolationService $service,
    ): RedirectResponse {
        $service->update($violation, $request->validated(), $request->user());

        return to_route('violations.index')->with('status', 'Pelanggaran diperbarui dengan transaksi koreksi ledger.');
    }

    public function destroy(Violation $violation, ViolationService $service): RedirectResponse
    {
        $service->delete($violation, request()->user());

        return to_route('violations.index')->with('status', 'Pelanggaran dihapus dari daftar dengan transaksi koreksi ledger.');
    }
}
