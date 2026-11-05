<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LogicException;

class ActivityLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'role_name',
        'action',
        'category',
        'subject_type',
        'subject_id',
        'old_values',
        'new_values',
        'before_data',
        'after_data',
        'metadata',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'before_data' => 'array',
            'after_data' => 'array',
            'old_values' => 'array',
            'new_values' => 'array',
            'metadata' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Activity logs are immutable.'));
        static::deleting(fn () => throw new LogicException('Activity logs are immutable.'));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function getNewValuesSummaryAttribute(): ?string
    {
        $values = $this->new_values;
        if ($values === null || $values === []) {
            return null;
        }

        if ($this->category === 'category') {
            $points = abs((int) ($values['points'] ?? 0));
            $type = ($values['type'] ?? null) === 'reward' ? 'Reward' : 'Pelanggaran';
            $label = match ($this->action) {
                'create' => 'Kategori Baru',
                'delete' => 'Kategori Dihapus',
                default => 'Kategori Diperbarui',
            };

            return sprintf(
                '%s: %s (%s%d Poin, %s)',
                $label,
                $values['name'] ?? 'Tanpa nama',
                $type === 'Reward' ? '+' : '-',
                $points,
                $type,
            );
        }

        if ($this->category === 'duty_replacement') {
            $status = match ($values['status'] ?? null) {
                'pending' => 'Pending',
                'approved' => 'Disetujui',
                'rejected' => 'Ditolak',
                default => 'Tidak diketahui',
            };

            if (! isset($values['original_user_id'], $values['replacement_user_id'])) {
                if (in_array($values['status'] ?? null, ['approved', 'rejected'], true)) {
                    return sprintf(
                        'Penggantian Anggota Piket: %s%s',
                        $status,
                        empty($values['decision_reason']) ? '' : ' (Alasan: '.$values['decision_reason'].')',
                    );
                }

                return sprintf(
                    'Penggantian Jadwal Piket #%s: Status %s%s',
                    $values['duty_schedule_id'] ?? '—',
                    $status,
                    empty($values['decision_reason']) ? '' : '; Alasan: '.$values['decision_reason'],
                );
            }

            return sprintf(
                'Penggantian Jadwal Piket #%s: User ID %s digantikan oleh User ID %s (%s)',
                $values['duty_schedule_id'] ?? '—',
                $values['original_user_id'] ?? '—',
                $values['replacement_user_id'] ?? '—',
                $status,
            );
        }

        if ($this->category === 'student_objection') {
            $status = match ($values['status'] ?? null) {
                'pending' => 'Pending',
                'approved' => 'Disetujui',
                'rejected' => 'Ditolak',
                default => 'Tidak diketahui',
            };
            if (($values['status'] ?? null) === 'pending') {
                return 'Status: Menunggu Persetujuan (Pending)';
            }

            $summary = sprintf(
                '%s (Keberatan poin dari %s atas Transaksi #%s)',
                $status,
                $values['student_id'] ?? 'Siswa',
                $values['source_id'] ?? '—',
            );
            if (! empty($values['decision_reason'])) {
                $summary .= ' (Alasan: '.$values['decision_reason'].')';
            }
            if (! empty($values['correction_transaction_id'])) {
                $summary .= '; Transaksi koreksi #'.$values['correction_transaction_id'];
            }

            return $summary;
        }

        $date = $values['violation_date']
            ?? $values['attendance_date']
            ?? $values['reward_date']
            ?? $values['awarded_date']
            ?? $values['transaction_date']
            ?? null;
        $points = $values['points'] ?? null;
        if (isset($values['student_id']) && $date !== null && $points !== null) {
            $type = match ($this->category) {
                'lateness_record' => 'Terlambat',
                'morning_reward' => 'Penghargaan Pagi',
                'additional_point' => $values['reason'] ?? 'Poin Tambahan',
                'point_transaction' => $values['category_id'] ?? 'Transaksi Poin',
                default => $values['category_id'] ?? 'Pelanggaran',
            };

            return implode(', ', [
                (string) $values['student_id'],
                (string) $type,
                \Illuminate\Support\Carbon::parse($date)->format('d/m/Y'),
                sprintf('%+d', (int) $points),
            ]);
        }

        if ($this->category === 'duty_attendance' && isset($values['student_id'])) {
            return sprintf(
                'Kehadiran Piket Jadwal #%s: %s, %+d Poin',
                $values['duty_schedule_id'] ?? '—',
                $values['student_id'],
                (int) ($values['points'] ?? 0),
            );
        }

        if ($this->category === 'duty_schedule') {
            $members = $values['member_user_ids'] ?? [];
            $memberCount = is_array($members) ? count($members) : 0;
            $date = isset($values['duty_date'])
                ? \Illuminate\Support\Carbon::parse($values['duty_date'])->format('d/m/Y')
                : null;

            return sprintf(
                'Jadwal Piket%s: %d anggota',
                $date === null ? '' : ' '.$date,
                $memberCount,
            );
        }

        $labels = [
            'student_id' => 'Siswa',
            'category_id' => 'Jenis',
            'duty_schedule_id' => 'Jadwal Piket',
            'original_user_id' => 'Anggota awal',
            'replacement_user_id' => 'Anggota pengganti',
            'member_user_id' => 'Anggota piket',
            'member_user_ids' => 'Anggota piket',
            'source_type' => 'Jenis sumber',
            'source_id' => 'ID sumber',
            'correction_transaction_id' => 'ID transaksi koreksi',
            'rejection_reason' => 'Alasan penolakan',
            'requested_by' => 'Pengaju',
            'decided_by' => 'Pengambil keputusan',
            'cancelled_by' => 'Pembatal',
            'created_by' => 'Pencatat',
            'violation_date' => 'Tanggal',
            'attendance_date' => 'Tanggal',
            'reward_date' => 'Tanggal',
            'awarded_date' => 'Tanggal',
            'transaction_date' => 'Tanggal',
            'duty_date' => 'Tanggal piket',
            'week_start' => 'Awal minggu',
            'arrival_time' => 'Waktu tiba',
            'rank' => 'Peringkat',
            'points' => 'Poin',
            'name' => 'Nama',
            'type' => 'Jenis',
            'nis' => 'NIS',
            'nisn' => 'NISN',
            'gender' => 'Jenis kelamin',
            'grade' => 'Tingkat',
            'major' => 'Jurusan',
            'class_name' => 'Kelas',
            'is_active' => 'Status akun',
            'cancellation_reason' => 'Alasan pembatalan',
            'description' => 'Deskripsi',
            'reason' => 'Alasan',
            'decision_reason' => 'Alasan keputusan',
            'status' => 'Status',
        ];

        return collect($values)
            ->map(function (mixed $value, string $key) use ($labels): string {
                $label = $labels[$key] ?? str($key)->replace('_', ' ')->ucfirst()->toString();
                if ($key === 'points' && is_numeric($value)) {
                    $value = sprintf('%+d Poin', (int) $value);
                } elseif ($key === 'type' && is_string($value)) {
                    $value = match ($value) {
                        'reward' => 'Reward',
                        'violation' => 'Pelanggaran',
                        default => $value,
                    };
                } elseif ($key === 'source_type' && is_string($value)) {
                    $value = match ($value) {
                        'point_transaction' => 'Transaksi poin',
                        'morning_reward' => 'Penghargaan pagi',
                        'lateness_record' => 'Keterlambatan',
                        'student_objection' => 'Keberatan siswa',
                        default => $value,
                    };
                } elseif ($key === 'status' && is_string($value)) {
                    $value = match ($value) {
                        'pending' => 'Pending',
                        'approved' => 'Disetujui',
                        'rejected' => 'Ditolak',
                        default => $value,
                    };
                } elseif ($key === 'is_active' && is_bool($value)) {
                    $value = $value ? 'Aktif' : 'Nonaktif';
                } elseif (str_ends_with($key, '_date') && is_string($value)) {
                    $value = \Illuminate\Support\Carbon::parse($value)->format('d/m/Y');
                } elseif (is_array($value)) {
                    $value = implode(', ', array_map(
                        static fn (mixed $item): string => is_scalar($item) ? (string) $item : '',
                        $value,
                    ));
                }

                return $label.': '.$value;
            })
            ->implode('; ');
    }

    public function getOldValuesSummaryAttribute(): ?string
    {
        $values = $this->old_values;
        if ($values === null || $values === []) {
            return null;
        }

        if ($this->category === 'student_objection' && ($values['status'] ?? null) === 'pending') {
            return 'Status: Menunggu Persetujuan (Pending)';
        }

        if ($this->category === 'duty_replacement' && ($values['status'] ?? null) === 'pending') {
            return 'Status penggantian: Menunggu Persetujuan (Pending)';
        }

        return collect($values)
            ->filter(static fn (mixed $value): bool => $value !== null && $value !== '')
            ->map(function (mixed $value, string $key): string {
                $label = $this->valueLabel($key);
                $formatted = $this->formatValue($value, $key);

                if ($key === 'status' && in_array($this->category, ['student_objection', 'duty_replacement'], true)) {
                    return $formatted;
                }

                return $label.': '.$formatted;
            })
            ->implode('; ');
    }

    private function valueLabel(string $key): string
    {
        return [
            'student_id' => 'Siswa',
            'category_id' => 'Kategori',
            'duty_schedule_id' => 'Jadwal Piket',
            'original_user_id' => 'Anggota awal',
            'replacement_user_id' => 'Anggota pengganti',
            'source_type' => 'Jenis sumber',
            'source_id' => 'ID transaksi',
            'correction_transaction_id' => 'ID transaksi koreksi',
            'status' => 'Status',
            'decision_reason' => 'Alasan keputusan',
            'rejection_reason' => 'Alasan penolakan',
            'decided_by' => 'Pengambil keputusan',
            'decided_at' => 'Waktu keputusan',
            'requested_by' => 'Pengaju',
            'points' => 'Poin',
            'transaction_date' => 'Tanggal transaksi',
            'violation_date' => 'Tanggal pelanggaran',
            'attendance_date' => 'Tanggal keterlambatan',
            'reward_date' => 'Tanggal penghargaan',
            'awarded_date' => 'Tanggal pemberian',
            'name' => 'Nama',
            'type' => 'Jenis',
            'description' => 'Deskripsi',
            'reason' => 'Alasan',
            'supporting_note' => 'Catatan pendukung',
        ][$key] ?? str($key)->replace('_', ' ')->ucfirst()->toString();
    }

    private function formatValue(mixed $value, string $key): string
    {
        if (is_array($value)) {
            return collect($value)
                ->filter(static fn (mixed $item): bool => $item !== null && $item !== '')
                ->map(fn (mixed $item, string|int $itemKey): string => is_int($itemKey)
                    ? (is_scalar($item) ? (string) $item : '')
                    : $this->valueLabel((string) $itemKey).': '.(is_scalar($item) ? $this->formatValue($item, (string) $itemKey) : ''))
                ->filter()
                ->implode(', ');
        }

        if (is_bool($value)) {
            return $value ? 'Ya' : 'Tidak';
        }

        if ($key === 'status' && is_string($value)) {
            return match ($value) {
                'pending' => 'Status: Menunggu Persetujuan (Pending)',
                'approved' => 'Disetujui',
                'rejected' => 'Ditolak',
                default => $value,
            };
        }

        if ($key === 'type' && is_string($value)) {
            return match ($value) {
                'reward' => 'Reward',
                'violation' => 'Pelanggaran',
                default => $value,
            };
        }

        if (is_string($value) && str_ends_with($key, '_date')) {
            return \Illuminate\Support\Carbon::parse($value)->format('d/m/Y');
        }

        if (is_string($value) && str_ends_with($key, '_at')) {
            return \Illuminate\Support\Carbon::parse($value)->format('d/m/Y H:i');
        }

        if ($key === 'points' && is_numeric($value)) {
            return sprintf('%+d Poin', (int) $value);
        }

        return (string) $value;
    }
}
