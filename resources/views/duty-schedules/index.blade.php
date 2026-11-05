<x-app-layout>
    <x-slot name="header">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <h1 class="h3 fw-bold mb-1">Jadwal Piket Gerbang</h1>
                <p class="text-secondary mb-0">Jadwal Senin–Jumat, 4–6 petugas setiap hari.</p>
            </div>
            <a class="btn btn-outline-primary" href="{{ route('duty-replacements.index') }}">Penggantian anggota</a>
        </div>
    </x-slot>

    <div class="row g-4">
        <div class="col-12">
            <section class="card border-0 shadow-sm">
                <div class="card-body">
                    <form method="GET" action="{{ route('duty-schedules.index') }}" class="row g-3 align-items-end">
                        <div class="col-md-4">
                            <label for="period" class="form-label">Tampilan</label>
                            <select class="form-select" id="period" name="period">
                                <option value="week" @selected($period === 'week')>Mingguan</option>
                                <option value="month" @selected($period === 'month')>Bulanan</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="start_date" class="form-label">Tanggal acuan</label>
                            <input class="form-control" type="date" id="start_date" name="start_date"
                                value="{{ request('start_date', now('Asia/Jakarta')->toDateString()) }}" required>
                        </div>
                        <div class="col-md-4">
                            <button class="btn btn-outline-secondary w-100" type="submit">Tampilkan jadwal</button>
                        </div>
                    </form>

                    @if ($isScheduleManager)
                        <hr>
                        <form method="POST" action="{{ route('duty-schedules.preview') }}" class="row g-3 align-items-end">
                            @csrf
                            <input type="hidden" name="period" value="{{ $period }}">
                            <input type="hidden" name="start_date" value="{{ $startDate->toDateString() }}">
                            <div class="col">
                                <p class="mb-0">Buat pembagian acak yang seimbang. Preview dapat diedit sebelum disimpan.</p>
                            </div>
                            <div class="col-auto">
                                <button class="btn btn-primary" type="submit">Buat preview gacha</button>
                            </div>
                        </form>
                    @endif
                </div>
            </section>
        </div>

        <div class="col-12">
            <section class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3">
                    <h2 class="h5 fw-bold mb-0">
                        {{ $period === 'week' ? 'Jadwal Mingguan' : 'Jadwal Bulanan' }}
                        <span class="text-secondary fw-normal">{{ $startDate->translatedFormat('d M Y') }} – {{ $endDate->translatedFormat('d M Y') }}</span>
                    </h2>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead><tr><th>Tanggal</th><th>Petugas awal</th><th>Penggantian disetujui</th><th>Status</th></tr></thead>
                        <tbody>
                            @forelse ($schedules as $schedule)
                                <tr>
                                    <td class="text-nowrap">{{ $schedule->duty_date->translatedFormat('l, d M Y') }}</td>
                                    <td>
                                        @forelse ($schedule->members as $member)
                                            <span class="badge text-bg-light border me-1 mb-1">{{ $member->name }} ({{ $member->username }})</span>
                                        @empty
                                            <span class="text-secondary">Belum ada anggota</span>
                                        @endforelse
                                    </td>
                                    <td>
                                        @forelse ($schedule->replacements->where('status', 'approved') as $replacement)
                                            <div>{{ $replacement->originalUser->name }} → {{ $replacement->replacementUser->name }}</div>
                                        @empty
                                            <span class="text-secondary">—</span>
                                        @endforelse
                                    </td>
                                    <td><span class="badge {{ $schedule->status === 'scheduled' ? 'text-bg-success' : 'text-bg-secondary' }}">{{ ucfirst($schedule->status) }}</span></td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-secondary py-4">Belum ada jadwal tersimpan pada periode ini.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
