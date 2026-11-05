@php
    $actionLabels = [
        'create' => 'Tambah',
        'update' => 'Perbarui',
        'delete' => 'Hapus',
        'approve' => 'Setujui',
        'reject' => 'Tolak',
    ];
    $categoryLabels = [
        'additional_point' => 'Poin Tambahan',
        'category' => 'Kategori Poin',
        'duty_attendance' => 'Absensi Piket',
        'duty_replacement' => 'Penggantian Anggota Piket',
        'duty_schedule' => 'Jadwal Piket',
        'lateness_record' => 'Keterlambatan',
        'morning_reward' => 'Penghargaan Pagi',
        'point_transaction' => 'Transaksi Poin',
        'student' => 'Data Siswa',
        'student_objection' => 'Keberatan Siswa',
        'violation' => 'Pelanggaran',
    ];
@endphp
<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="h3 fw-bold mb-1">Activity Log</h1>
            <p class="text-secondary mb-0">Jejak audit bersifat hanya-baca dan tidak dapat diubah atau dihapus.</p>
        </div>
    </x-slot>

    <section class="card border-0 shadow-sm">
        <div class="card-body border-bottom">
            <form method="GET" action="{{ route('activity-logs.index') }}" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label" for="search">Pencarian</label>
                    <input class="form-control" id="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Aktor, kategori, atau ID data">
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="user_id">Pengguna</label>
                    <select class="form-select" id="user_id" name="user_id">
                        <option value="">Semua pengguna</option>
                        @foreach ($users as $user)
                            <option value="{{ $user->id }}" @selected(($filters['user_id'] ?? '') == $user->id)>{{ $user->name }} ({{ $user->username }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="action">Tindakan</label>
                    <select class="form-select" id="action" name="action">
                        <option value="">Semua tindakan</option>
                        @foreach (['create', 'update', 'delete', 'approve', 'reject'] as $action)
                            <option value="{{ $action }}" @selected(($filters['action'] ?? '') === $action)>{{ $actionLabels[$action] ?? ucfirst($action) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="date_from">Dari tanggal</label>
                    <input class="form-control" id="date_from" name="date_from" type="date" value="{{ $filters['date_from'] ?? '' }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="date_to">Sampai tanggal</label>
                    <input class="form-control" id="date_to" name="date_to" type="date" value="{{ $filters['date_to'] ?? '' }}">
                </div>
                <div class="col-12 d-flex justify-content-end gap-2">
                    <a class="btn btn-outline-secondary" href="{{ route('activity-logs.index') }}">Reset</a>
                    <button class="btn btn-primary" type="submit">Terapkan filter</button>
                </div>
            </form>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 activity-log-table">
                <thead><tr><th>Waktu</th><th>Aktor</th><th>Tindakan</th><th>Kategori</th><th>ID Data</th><th>Nilai Sebelum</th><th>Nilai Sesudah</th></tr></thead>
                <tbody>
                    @forelse ($logs as $log)
                        <tr>
                            <td class="align-middle text-nowrap">
                                {{ $log->occurred_at->timezone('Asia/Jakarta')->format('d/m/Y') }}<br>
                                <span class="text-muted small">{{ $log->occurred_at->timezone('Asia/Jakarta')->format('H:i:s') }} WIB</span>
                            </td>
                            <td>{{ $log->user?->name ?? 'Akun dihapus' }}<span class="d-block small text-secondary">{{ $log->role_name }}</span></td>
                            <td><span class="badge text-bg-secondary">{{ $actionLabels[$log->action] ?? ucfirst($log->action) }}</span></td>
                            <td>{{ $categoryLabels[$log->category] ?? $categoryLabels[$log->subject_type] ?? $log->category ?? $log->subject_type }}</td>
                            <td>{{ $log->subject_id ?? '—' }}</td>
                            <td>
                                @if ($log->old_values)
                                    {{ $log->old_values_summary }}
                                @else
                                    <span class="text-secondary">—</span>
                                @endif
                            </td>
                            <td>
                                @if ($log->new_values_summary)
                                    {{ $log->new_values_summary }}
                                @else
                                    <span class="text-secondary">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-secondary py-4">Belum ada aktivitas yang tercatat.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-body">{{ $logs->links() }}</div>
    </section>
</x-app-layout>
