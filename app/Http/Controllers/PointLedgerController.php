<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StorePointLedgerRequest;
use App\Models\Category;
use App\Models\PointLedger;
use App\Models\Student;
use App\Services\ActivityLogService;
use App\Services\PointTransactionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PointLedgerController extends Controller
{
    public function create(): View
    {
        $this->authorize('create', PointLedger::class);

        return view('ledgers.create', [
            'students' => Student::query()->with('user')->orderBy('class_name')->orderBy('nisn')->get(),
            'categories' => Category::query()->orderBy('type')->orderBy('name')->get(),
            'selectedStudent' => request()->integer('student_id'),
        ]);
    }

    public function store(
        StorePointLedgerRequest $request,
        PointTransactionService $transactions,
        ActivityLogService $activityLogs,
    ): RedirectResponse {
        $this->authorize('create', PointLedger::class);

        $validated = $request->validated();

        $category = Category::query()->whereKey($validated['category_id'])->firstOrFail();
        $student = Student::query()->whereKey($validated['student_id'])->firstOrFail();
        $points = $category->type === 'violation'
            ? -abs($category->points)
            : abs($category->points);
        $ledger = $transactions->record(
            $student,
            $category,
            $request->user(),
            null,
            null,
            $points,
            $validated['description'] ?? $category->name,
            $validated['transaction_date'],
        );
        $activityLogs->record($request->user(), 'create', 'point_transaction', $ledger, null, [
            'student_id' => $ledger->student_id,
            'category_id' => $category->id,
            'type' => $ledger->type,
            'points' => $ledger->points,
            'transaction_date' => $ledger->transaction_date->toDateString(),
        ]);

        return to_route('students.show', $ledger->student_id)
            ->with('status', 'Transaksi poin berhasil dicatat.');
    }
}
