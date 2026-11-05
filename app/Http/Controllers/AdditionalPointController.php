<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreAdditionalPointRequest;
use App\Http\Requests\UpdateAdditionalPointRequest;
use App\Models\AdditionalPoint;
use App\Models\Student;
use App\Services\AdditionalPointService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class AdditionalPointController extends Controller
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

        return view('additional-points.index', [
            'filters' => $filters,
            'points' => AdditionalPoint::query()
                ->with(['student.user', 'givenBy', 'pointTransaction'])
                ->when(isset($filters['start_date']), fn (Builder $query) => $query->whereDate('awarded_date', '>=', $filters['start_date']))
                ->when(isset($filters['end_date']), fn (Builder $query) => $query->whereDate('awarded_date', '<=', $filters['end_date']))
                ->when(isset($filters['month']) && $filters['month'] !== '', function (Builder $query) use ($filters): void {
                    $month = Carbon::createFromFormat('Y-m', $filters['month'], 'Asia/Jakarta');
                    $query->whereYear('awarded_date', $month->year)->whereMonth('awarded_date', $month->month);
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
                ->orderByDesc('awarded_date')
                ->orderByDesc('id')
                ->paginate(20)
                ->withQueryString(),
            'classes' => Student::query()->distinct()->orderBy('class_name')->pluck('class_name'),
        ]);
    }

    public function create(): View
    {
        return view('additional-points.create', [
            'students' => Student::query()->with('user')->orderBy('class_name')->orderBy('nis')->get(),
        ]);
    }

    public function store(StoreAdditionalPointRequest $request, AdditionalPointService $service): RedirectResponse
    {
        $service->create($request->validated(), $request->user());

        return to_route('additional-points.index')->with('status', 'Poin tambahan berhasil dicatat.');
    }

    public function edit(AdditionalPoint $additional_point): View
    {
        $additional_point->load('student.user');

        return view('additional-points.edit', [
            'point' => $additional_point,
            'students' => Student::query()->with('user')->orderBy('class_name')->orderBy('nis')->get(),
        ]);
    }

    public function update(
        UpdateAdditionalPointRequest $request,
        AdditionalPoint $additional_point,
        AdditionalPointService $service,
    ): RedirectResponse {
        $service->update($additional_point, $request->validated(), $request->user());

        return to_route('additional-points.index')->with('status', 'Poin tambahan diperbarui dengan transaksi koreksi ledger.');
    }

    public function destroy(AdditionalPoint $additional_point, AdditionalPointService $service): RedirectResponse
    {
        $service->delete($additional_point, request()->user());

        return to_route('additional-points.index')->with('status', 'Poin tambahan dihapus dengan transaksi koreksi ledger.');
    }
}
