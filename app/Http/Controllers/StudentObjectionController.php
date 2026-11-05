<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ReviewStudentObjectionRequest;
use App\Http\Requests\StoreStudentObjectionRequest;
use App\Models\StudentObjection;
use App\Services\StudentObjectionService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class StudentObjectionController extends Controller
{
    public function create(Request $request): View
    {
        abort_unless($request->user()->isActive() && $request->user()->isSiswa(), 403);
        $student = $request->user()->student;
        abort_if($student === null, 403, 'Akun siswa belum terhubung ke profil siswa.');

        return view('objections.create', [
            'transactions' => $student->pointTransactions()
                ->with('category')
                ->where('is_active', true)
                ->latest('transaction_date')
                ->latest('id')
                ->get(),
            'objections' => $student->objections()
                ->with(['source.category', 'decidedBy', 'correctionTransaction'])
                ->latest()
                ->limit(20)
                ->get(),
        ]);
    }

    public function index(Request $request): View
    {
        abort_unless($request->user()->isActive()
            && ($request->user()->isGuru() || $request->user()->isOperasional()), 403);
        $filters = $request->validate([
            'status' => ['nullable', 'in:pending,approved,rejected'],
        ]);

        return view('objections.index', [
            'filters' => $filters,
            'objections' => StudentObjection::query()
                ->with(['student.user', 'source.category', 'decidedBy', 'correctionTransaction'])
                ->when(isset($filters['status']), fn (Builder $query) => $query->where('status', $filters['status']))
                ->latest()
                ->paginate(25)
                ->withQueryString(),
        ]);
    }

    public function store(
        StoreStudentObjectionRequest $request,
        StudentObjectionService $service,
    ): RedirectResponse {
        $student = $request->user()->student;
        abort_if($student === null, 403, 'Akun siswa belum terhubung ke profil siswa.');
        $evidencePath = $request->file('evidence')?->store('student-objections', 'local');
        $service->submit(
            $student,
            $request->integer('point_transaction_id'),
            $request->validated('reason'),
            $request->validated('supporting_note'),
            $evidencePath,
        );

        return to_route('dashboard')->with('status', 'Pengajuan keberatan berhasil dikirim.');
    }

    public function decide(
        ReviewStudentObjectionRequest $request,
        StudentObjection $objection,
        StudentObjectionService $service,
    ): RedirectResponse {
        $service->decide(
            $objection,
            $request->user(),
            $request->validated('status'),
            $request->validated('decision_reason'),
        );

        return to_route('objections.index')->with(
            'status',
            $request->validated('status') === 'approved'
                ? 'Keberatan disetujui dan transaksi koreksi ledger berhasil dibuat.'
                : 'Keberatan ditolak dengan alasan peninjauan tersimpan.',
        );
    }

    public function evidence(Request $request, StudentObjection $objection)
    {
        abort_unless($request->user()->isActive()
            && ($request->user()->isGuru() || $request->user()->isOperasional()), 403);
        abort_if($objection->evidence_path === null, 404);
        abort_unless(Storage::disk('local')->exists($objection->evidence_path), 404);

        return Storage::disk('local')->download($objection->evidence_path);
    }
}
