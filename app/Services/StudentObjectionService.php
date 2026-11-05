<?php

declare(strict_types=1);

namespace App\Services;

use App\Events\StudentObjectionDecided;
use App\Models\PointTransaction;
use App\Models\Student;
use App\Models\StudentObjection;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StudentObjectionService
{
    public function __construct(
        private readonly PointTransactionService $transactions,
        private readonly ActivityLogService $activityLogs,
        private readonly InternalNotificationService $notifications,
    ) {}

    public function submit(
        Student $student,
        int $transactionId,
        string $reason,
        ?string $supportingNote,
        ?string $evidencePath,
    ): StudentObjection {
        return DB::transaction(function () use ($student, $transactionId, $reason, $supportingNote, $evidencePath): StudentObjection {
            $lockedStudent = Student::query()->whereKey($student->id)->lockForUpdate()->firstOrFail();
            $transaction = PointTransaction::query()
                ->whereKey($transactionId)
                ->where('student_id', $lockedStudent->id)
                ->lockForUpdate()
                ->first();

            if ($transaction === null) {
                throw ValidationException::withMessages([
                    'point_transaction_id' => 'Transaksi yang dipilih bukan milik akun siswa Anda.',
                ]);
            }

            if (StudentObjection::query()
                ->where('student_id', $lockedStudent->id)
                ->where('source_type', 'point_transaction')
                ->where('source_id', $transaction->id)
                ->where('status', 'pending')
                ->exists()) {
                throw ValidationException::withMessages([
                    'point_transaction_id' => 'Transaksi ini sudah memiliki pengajuan keberatan yang menunggu peninjauan.',
                ]);
            }

            $objection = StudentObjection::query()->create([
                'student_id' => $lockedStudent->id,
                'source_type' => 'point_transaction',
                'source_id' => $transaction->id,
                'reason' => $reason,
                'supporting_note' => $supportingNote,
                'evidence_path' => $evidencePath,
                'status' => 'pending',
            ]);
            $this->activityLogs->record($lockedStudent->user, 'create', 'student_objection', $objection, null, [
                'student_id' => $lockedStudent->id,
                'source_type' => 'point_transaction',
                'source_id' => $transaction->id,
                'status' => 'pending',
            ]);

            return $objection;
        });
    }

    public function decide(
        StudentObjection $objection,
        User $actor,
        string $status,
        string $decisionReason,
    ): StudentObjection {
        $decided = DB::transaction(function () use ($objection, $actor, $status, $decisionReason): StudentObjection {
            $lockedObjection = StudentObjection::query()->whereKey($objection->id)->lockForUpdate()->firstOrFail();
            if ($lockedObjection->status !== 'pending') {
                throw ValidationException::withMessages([
                    'status' => 'Pengajuan ini sudah ditinjau dan tidak dapat diputuskan kembali.',
                ]);
            }

            if ($lockedObjection->source_type !== 'point_transaction') {
                throw ValidationException::withMessages([
                    'objection' => 'Jenis sumber keberatan belum didukung untuk koreksi otomatis.',
                ]);
            }

            $student = Student::query()->whereKey($lockedObjection->student_id)->lockForUpdate()->firstOrFail();
            $original = PointTransaction::query()
                ->whereKey($lockedObjection->source_id)
                ->where('student_id', $student->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedObjection->source_type === 'point_transaction'
                && $lockedObjection->source_id !== $original->id) {
                throw ValidationException::withMessages([
                    'objection' => 'Transaksi sumber tidak cocok dengan siswa yang mengajukan keberatan.',
                ]);
            }

            if ($status === 'approved') {
                if ($original->points === 0) {
                    throw ValidationException::withMessages([
                        'status' => 'Transaksi bernilai nol tidak dapat dikoreksi melalui persetujuan keberatan.',
                    ]);
                }

                $correction = $this->transactions->record(
                    $student,
                    $original->category()->firstOrFail(),
                    $actor,
                    'student_objection',
                    $lockedObjection->id,
                    -$original->points,
                    'Koreksi transaksi #'.$original->id.' atas keberatan #'.$lockedObjection->id.': '.$decisionReason,
                    today('Asia/Jakarta')->toDateString(),
                    $original->id,
                );
                $lockedObjection->correction_transaction_id = $correction->id;
            }

            $oldValues = $lockedObjection->only(['status', 'decision_reason', 'decided_by', 'decided_at']);
            $lockedObjection->forceFill([
                'status' => $status,
                'decision_reason' => $decisionReason,
                'decided_by' => $actor->id,
                'decided_at' => now('Asia/Jakarta'),
            ])->save();
            $this->activityLogs->record(
                $actor,
                $status === 'approved' ? 'approve' : 'reject',
                'student_objection',
                $lockedObjection,
                $oldValues,
                [
                    'status' => $status,
                    'decision_reason' => $decisionReason,
                    'correction_transaction_id' => $lockedObjection->correction_transaction_id,
                ],
            );
            $this->notifications->send(
                [$student->user],
                'student_objection.'.$status,
                $status === 'approved' ? 'Keberatan disetujui' : 'Keberatan ditolak',
                $status === 'approved'
                    ? 'Keberatan Anda disetujui. Transaksi koreksi poin telah dicatat.'
                    : 'Keberatan Anda ditolak. Alasan: '.$decisionReason,
                route('objections.create'),
                ['objection_id' => $lockedObjection->id, 'status' => $status],
            );

            return $lockedObjection->fresh(['student.user', 'decidedBy', 'correctionTransaction']);
        });

        StudentObjectionDecided::dispatch($decided);

        return $decided;
    }
}
