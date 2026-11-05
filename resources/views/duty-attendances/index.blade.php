<x-app-layout>
    <x-slot name="header">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <h1 class="h3 fw-bold mb-1">Absensi Piket Gerbang</h1>
                <p class="text-secondary mb-0">Hanya anggota jadwal atau pengganti yang disetujui yang dapat dicatat hadir. Poin +10 diberikan otomatis.</p>
            </div>
            <a class="btn btn-outline-primary" href="{{ route('duty-schedules.index') }}">Lihat jadwal</a>
        </div>
    </x-slot>

    @if ($schedule)
        <section class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3">
                <h2 class="h5 fw-bold mb-0">Presensi Hari Ini · {{ $schedule->duty_date->translatedFormat('l, d F Y') }}</h2>
            </div>
            <div class="card-body">
                @if ($eligibleMembers->isEmpty())
                    <div class="alert alert-warning mb-0">Belum ada anggota sah dengan akun siswa penerima poin yang ditautkan pada jadwal.</div>
                @else
                    <div class="row g-3">
                        @foreach ($eligibleMembers as $entry)
                            <div class="col-md-6 col-xl-4">
                                <article class="card h-100 {{ $entry['attended'] ? 'border-success bg-success-subtle' : '' }}">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start gap-2">
                                            <div>
                                                <h3 class="h6 fw-bold mb-1">{{ $entry['user']->name }}</h3>
                                                <p class="small text-secondary mb-2">
                                                    {{ $entry['replacement'] ? 'Pengganti disetujui' : 'Anggota jadwal' }}
                                                </p>
                                            </div>
                                            @if ($entry['attended'])
                                                <span class="badge text-bg-success">Hadir · +10</span>
                                            @endif
                                        </div>
                                        <p class="mb-3">Poin masuk ke {{ $entry['student']->user->name }} ({{ $entry['student']->nis }}).</p>
                                        @if ($canRecord && ! $entry['attended'])
                                            <form method="POST" action="{{ route('duty-attendances.store') }}">
                                                @csrf
                                                <input type="hidden" name="duty_schedule_id" value="{{ $schedule->id }}">
                                                <input type="hidden" name="member_user_id" value="{{ $entry['user']->id }}">
                                                <button class="btn btn-success w-100" type="submit">Catat hadir dan berikan +10</button>
                                            </form>
                                        @elseif (! $entry['attended'])
                                            <span class="badge text-bg-secondary">Pencatatan khusus akun operasional</span>
                                        @endif
                                    </div>
                                </article>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </section>
    @else
        <div class="alert alert-info">Tidak ada jadwal piket aktif untuk hari ini.</div>
    @endif

    <section class="card border-0 shadow-sm">
        <div class="card-header bg-white py-3">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                <h2 class="h5 fw-bold mb-0">Rekapitulasi Kehadiran</h2>
                <form method="GET" action="{{ route('duty-attendances.index') }}" class="d-flex flex-wrap gap-2">
                    <select class="form-select" name="period" aria-label="Periode rekap">
                        <option value="day" @selected($filters['period'] === 'day')>Harian</option>
                        <option value="month" @selected($filters['period'] === 'month')>Bulanan</option>
                        <option value="year" @selected($filters['period'] === 'year')>Tahunan</option>
                    </select>
                    <input class="form-control" type="date" name="date" value="{{ $filters['date'] }}" aria-label="Tanggal acuan">
                    <button class="btn btn-outline-primary" type="submit">Tampilkan</button>
                </form>
            </div>
            <p class="small text-secondary mb-0 mt-2">
                Periode {{ $start->translatedFormat('d M Y') }} – {{ $end->translatedFormat('d M Y') }}:
                <strong>{{ $attendanceCount }} kehadiran</strong> · <strong>{{ $pointsAwarded }} poin</strong>
            </p>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead><tr><th>Waktu</th><th>Tanggal Jadwal</th><th>Anggota Hadir</th><th>Siswa Penerima Poin</th><th>Dicatat Oleh</th><th>Poin</th></tr></thead>
                <tbody>
                    @forelse ($attendances as $attendance)
                        <tr>
                            <td>{{ $attendance->attended_at->timezone('Asia/Jakarta')->format('H:i') }} WIB</td>
                            <td>{{ $attendance->schedule->duty_date->translatedFormat('d M Y') }}</td>
                            <td>{{ $attendance->member?->name ?? 'Data lama' }}</td>
                            <td>{{ $attendance->student->user->name }} ({{ $attendance->student->nis }})</td>
                            <td>{{ $attendance->recordedBy->name }}</td>
                            <td><span class="badge text-bg-success">+10</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-secondary py-4">Belum ada kehadiran pada periode ini.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-body">{{ $attendances->links() }}</div>
    </section>
</x-app-layout>
