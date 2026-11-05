<x-app-layout>
    <x-slot name="header">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
                <p class="text-uppercase small fw-semibold text-primary mb-1">SMKN 20 Jakarta</p>
                <h1 class="h3 fw-bold mb-0">
                    @switch($dashboardRole)
                        @case('guru') Dashboard Guru @break
                        @case('operasional') Dashboard Operasional Piket @break
                        @default Dashboard Pribadi
                    @endswitch
                </h1>
                @if ($dashboardRole === 'operasional')
                    <p class="small text-white-50 mb-0">Akun petugas OSIS/MPK</p>
                @endif
            </div>
            <span class="badge rounded-pill text-bg-light border px-3 py-2">{{ now()->translatedFormat('l, d F Y') }}</span>
        </div>
    </x-slot>

    @if ($dashboardRole === 'guru')
        <div class="d-flex flex-wrap align-items-end justify-content-between gap-3 mb-4">
            <div>
                <h2 class="h5 fw-bold mb-1">Ringkasan kedisiplinan</h2>
                <p class="text-secondary mb-0">Statistik bersumber dari catatan operasional dalam database.</p>
            </div>
            <form method="GET" action="{{ route('dashboard') }}" class="row g-2 align-items-end">
                <div class="col-auto">
                    <label class="form-label small mb-1" for="start_date">Dari tanggal</label>
                    <input class="form-control form-control-sm" id="start_date" name="start_date" type="date" value="{{ $startDateInput }}">
                </div>
                <div class="col-auto">
                    <label class="form-label small mb-1" for="end_date">Sampai tanggal</label>
                    <input class="form-control form-control-sm" id="end_date" name="end_date" type="date" value="{{ $endDateInput }}">
                </div>
                <div class="col-auto"><button class="btn btn-primary btn-sm">Terapkan</button></div>
            </form>
        </div>

        <div class="row g-3 mb-4">
            @foreach ([
                ['Total siswa aktif', $studentCount, 'primary'],
                ['Pelanggaran Hari Ini', $violationCount, 'danger'],
                ['Keterlambatan Hari Ini', $latenessCount, 'warning'],
                ['Penghargaan Pagi', $morningRewardCount, 'success'],
                ['Kehadiran Piket Hari Ini', $attendanceCount, 'info'],
                ['Total saldo poin siswa', number_format($totalPoints), 'primary'],
            ] as [$label, $value, $color])
                <div class="col-6 col-xl-4">
                    <article class="card sijaga-stat-card">
                        <div class="card-body d-flex align-items-center gap-3 p-3 p-lg-4">
                            <span class="rounded-circle bg-{{ $color }}-subtle text-{{ $color }} p-3 fw-bold" aria-hidden="true">{{ substr($label, 0, 1) }}</span>
                            <div>
                                <p class="small text-secondary mb-1">{{ $label }}</p>
                                <p class="h3 fw-bold mb-0">{{ number_format((int) $value) }}</p>
                            </div>
                        </div>
                    </article>
                </div>
            @endforeach
        </div>

        <div class="row g-4">
            <div class="col-xl-7">
                <section class="card border-0 shadow-sm h-100">
                    <div class="card-body p-3 p-lg-4">
                        <div class="mb-3">
                            <h2 class="h5 fw-bold mb-1">Jenis pelanggaran terbanyak</h2>
                            <p class="small text-secondary mb-0">Periode {{ \Illuminate\Support\Carbon::parse($startDate)->format('d/m/Y') }} – {{ \Illuminate\Support\Carbon::parse($endDate)->format('d/m/Y') }}</p>
                        </div>
                        @if ($topCategories->sum('violations_count'))
                            <div class="sijaga-chart-wrap"><canvas data-chart="teacher-category-config" aria-label="Grafik jumlah pelanggaran per kategori"></canvas></div>
                            <script type="application/json" id="teacher-category-config">@json($categoryChartConfig)</script>
                        @else
                            <div class="text-center py-5 text-secondary">Belum ada data pelanggaran pada periode ini.</div>
                        @endif
                    </div>
                </section>
            </div>
            <div class="col-xl-5">
                <section class="card border-0 shadow-sm h-100">
                    <div class="card-body p-3 p-lg-4">
                        <h2 class="h5 fw-bold mb-1">Ringkasan pencatatan</h2>
                        <p class="small text-secondary mb-4">{{ number_format($transactionCount) }} transaksi poin pada periode terpilih.</p>
                        <div class="d-grid gap-2">
                            <a class="btn btn-outline-primary text-start" href="{{ route('students.index') }}">Buka data siswa <span class="float-end">&rarr;</span></a>
                            <a class="btn btn-outline-primary text-start" href="{{ route('reports.index') }}">Lihat laporan kedisiplinan <span class="float-end">&rarr;</span></a>
                            <a class="btn btn-outline-primary text-start" href="{{ route('additional-points.create') }}">Beri poin tambahan <span class="float-end">&rarr;</span></a>
                        </div>
                        <div class="alert alert-light border mt-4 mb-0 small">
                            Periode statistik dapat diubah menggunakan filter tanggal di bagian atas.
                        </div>
                    </div>
                </section>
            </div>
        </div>

        <section id="morning-award" class="card border-0 shadow-sm mt-4">
            <div class="card-body p-3 p-lg-4">
                <div class="d-flex justify-content-between align-items-center gap-2 mb-3">
                    <div>
                        <h2 class="h5 fw-bold mb-1">Pencatatan Penghargaan Pagi</h2>
                        <p class="small text-secondary mb-0">Pemberian otomatis +15 poin untuk siswa.</p>
                    </div>
                    <a class="btn btn-sm btn-outline-primary" href="#morning-award-form">Buka form</a>
                </div>
                @include('dashboard.partials.morning-reward-form')
            </div>
        </section>
        @if ($todayRewards->isNotEmpty())
            <section class="card border-0 shadow-sm mt-4">
                <div class="card-body p-3 p-lg-4">
                    <h2 class="h5 fw-bold mb-3">Penerima Penghargaan Pagi Hari Ini</h2>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead><tr><th>Peringkat</th><th>Siswa</th><th>Waktu tiba</th><th>Pencatat</th><th></th></tr></thead>
                            <tbody>
                                @foreach ($todayRewards as $reward)
                                    <tr>
                                        <td>{{ $reward->rank }}</td>
                                        <td>{{ $reward->student->user->name }} — {{ $reward->student->nis }}</td>
                                        <td>{{ substr((string) $reward->arrival_time, 0, 5) }} WIB</td>
                                        <td>{{ $reward->recordedBy->name }}</td>
                                        <td><a class="btn btn-sm btn-outline-primary" href="{{ route('morning-rewards.edit', $reward) }}">Edit</a></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        @endif
    @elseif ($dashboardRole === 'operasional')
        <div class="row g-3 mb-4">
            @foreach ([
                ['Total siswa aktif', $studentCount, 'primary', 'people'],
                ['Pelanggaran Hari Ini', $todayViolationCount, 'danger', 'exclamation-triangle'],
                ['Keterlambatan Hari Ini', $todayLatenessCount, 'warning', 'clock-history'],
                ['Kehadiran Piket Hari Ini', $todayAttendanceCount, 'info', 'clipboard-check'],
            ] as [$label, $value, $color, $icon])
                <div class="col-6 col-xl-3">
                    <article class="card sijaga-stat-card">
                        <div class="card-body d-flex align-items-center gap-3 p-3 p-lg-4">
                            <span class="rounded-circle bg-{{ $color }}-subtle text-{{ $color }} p-3" aria-hidden="true">
                                <i class="bi bi-{{ $icon }}"></i>
                            </span>
                            <div>
                                <p class="small text-secondary mb-1">{{ $label }}</p>
                                <p class="h3 fw-bold mb-0">{{ number_format((int) $value) }}</p>
                            </div>
                        </div>
                    </article>
                </div>
            @endforeach
        </div>
        <div class="row g-4">
            <div class="col-xl-7">
                <section class="card border-0 shadow-sm h-100">
                    <div class="card-body p-3 p-lg-4">
                        <div class="d-flex flex-wrap justify-content-between gap-2 mb-3">
                            <div>
                                <h2 class="h5 fw-bold mb-1">Jadwal piket hari ini</h2>
                                <p class="small text-secondary mb-0">{{ now()->translatedFormat('l, d F Y') }}</p>
                            </div>
                            @if ($schedule)
                                <span class="badge text-bg-success align-self-start">{{ $schedule->members->count() }} petugas terjadwal</span>
                            @endif
                        </div>
                        <a class="btn btn-sm btn-outline-primary mb-3" href="{{ route('duty-attendances.index') }}">Buka pencatatan absensi piket</a>
                        @if ($schedule)
                            @if ($schedule->members->isNotEmpty())
                                <div class="table-responsive">
                                    <table class="table align-middle">
                                        <thead><tr><th>Petugas</th><th>Siswa terhubung</th><th>Status presensi</th></tr></thead>
                                        <tbody>
                                            @foreach ($schedule->members as $member)
                                                @php
                                                    $replacement = $schedule->replacements->first(fn ($item) => $item->status === 'approved' && $item->original_user_id === $member->id);
                                                    $assignedMember = $replacement?->replacementUser ?? $member;
                                                    $linkedStudent = $replacement?->replacementStudent ?? $scheduleStudents->get($member->pivot->student_id);
                                                    $hasAttended = $schedule->attendances->contains(
                                                        fn ($attendance) => $attendance->member_user_id === $assignedMember->id
                                                            || ($attendance->member_user_id === null && $attendance->student_id === $member->pivot->student_id),
                                                    );
                                                @endphp
                                                <tr>
                                                    <td class="fw-semibold">
                                                        {{ $assignedMember->name }}
                                                        @if ($replacement)
                                                            <span class="badge text-bg-info">Menggantikan {{ $member->name }}</span>
                                                        @endif
                                                    </td>
                                                    <td>{{ $linkedStudent?->user?->name ?? 'Belum ditautkan' }}</td>
                                                    <td>
                                                        @if ($hasAttended)
                                                            <span class="badge text-bg-success">Hadir</span>
                                                        @elseif ($member->pivot->student_id)
                                                            <span class="badge text-bg-warning">Belum hadir</span>
                                                        @else
                                                            <span class="badge text-bg-secondary">Belum ditautkan ke NIS</span>
                                                        @endif
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @else
                                <div class="alert alert-warning mb-0">Jadwal hari ini belum memiliki anggota.</div>
                            @endif
                            @if ($schedule->attendances->isNotEmpty())
                                <h3 class="h6 fw-bold mt-4">Presensi tercatat</h3>
                                <ul class="list-group list-group-flush">
                                    @foreach ($schedule->attendances as $attendance)
                                        <li class="list-group-item px-0 d-flex justify-content-between">
                                            <span>{{ $attendance->student->user->name }} ({{ $attendance->student->nis }})</span>
                                            <span class="text-secondary small">{{ $attendance->attended_at->format('H:i') }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        @else
                            <div class="text-center py-5">
                                <p class="h6 fw-semibold">Tidak ada jadwal piket untuk hari ini.</p>
                                <p class="small text-secondary mb-0">Informasi jadwal akan tampil di sini jika tersedia.</p>
                            </div>
                        @endif
                    </div>
                </section>
            </div>
            <div class="col-xl-5">
                <section id="morning-award" class="card border-0 shadow-sm h-100">
                    <div class="card-body p-3 p-lg-4">
                        <h2 class="h5 fw-bold mb-1">Penghargaan Pagi</h2>
                        <p class="small text-secondary mb-3">Catat siswa yang tiba pukul 04.00–05.30. Poin +15 tersimpan otomatis.</p>
                        @include('dashboard.partials.morning-reward-form')
                    </div>
                </section>
            </div>
        </div>

        <section class="card border-0 shadow-sm mt-4">
            <div class="card-body p-3 p-lg-4">
                <h2 class="h5 fw-bold mb-3">Penghargaan yang dicatat hari ini</h2>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead><tr><th>Peringkat</th><th>Siswa</th><th>Waktu tiba</th><th>Dicatat oleh</th><th>Poin</th></tr></thead>
                        <tbody>
                            @forelse ($todayRewards as $reward)
                                <tr>
                                    <td>{{ $reward->rank }}</td>
                                    <td>{{ $reward->student->user->name }} <span class="text-secondary small">({{ $reward->student->nis }})</span></td>
                                    <td>{{ substr($reward->arrival_time, 0, 5) }}</td>
                                    <td>{{ $reward->recordedBy->name }}</td>
                                    <td><span class="badge text-bg-success">+15</span></td>
                                </tr>
                            @empty
                                <tr><td class="text-center text-secondary py-4" colspan="5">Belum ada penghargaan yang dicatat hari ini.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    @else
        <div class="row g-4">
            <div class="col-xl-4">
                <section class="card border-0 shadow-sm h-100">
                    <div class="card-body p-4">
                        <p class="small text-uppercase text-secondary fw-semibold mb-1">Ringkasan poin saya · Saldo poin saat ini</p>
                        @php
                            $studentPointBadge = $student->current_points >= 100 ? 'success' : ($student->current_points >= 75 ? 'warning' : 'danger');
                        @endphp
                        <p class="mb-2"><span class="badge text-bg-{{ $studentPointBadge }} fs-2">{{ number_format($student->current_points) }}</span></p>
                        <p class="text-secondary mb-0">{{ $student->user->name }} · NIS {{ $student->nis }} · NISN {{ $student->nisn }} · {{ $student->class_name }}</p>
                        <hr>
                        <p class="small mb-1">Saldo awal <strong>{{ $student->initial_points }}</strong> poin</p>
                        <a class="btn btn-primary mt-3" href="{{ route('objections.create') }}">Ajukan keberatan</a>
                    </div>
                </section>
            </div>
            <div class="col-xl-8">
                <section class="card border-0 shadow-sm h-100">
                    <div class="card-body p-3 p-lg-4">
                        <h2 class="h5 fw-bold mb-1">Perkembangan poin</h2>
                        <p class="small text-secondary">Saldo kumulatif berdasarkan transaksi 30 hari terakhir.</p>
                        <div class="sijaga-chart-wrap"><canvas data-chart="student-points-config" aria-label="Grafik perkembangan saldo poin"></canvas></div>
                        <script type="application/json" id="student-points-config">@json($pointChartConfig)</script>
                    </div>
                </section>
            </div>
        </div>

        <div class="row g-4 mt-1">
            <div class="col-xl-6">
                <section class="card border-0 shadow-sm h-100">
                    <div class="card-body p-3 p-lg-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h2 class="h5 fw-bold mb-0">Riwayat poin terbaru</h2>
                            <a href="{{ route('students.show', $student) }}" class="small">Semua riwayat</a>
                        </div>
                        <section class="card border-0 shadow-sm mt-4">
                            <div class="card-body p-3 p-lg-4">
                                <div class="d-flex justify-content-between align-items-center gap-2 mb-3">
                                    <h2 class="h5 fw-bold mb-0">Status keberatan saya</h2>
                                    <a class="btn btn-sm btn-outline-primary" href="{{ route('objections.create') }}">Ajukan keberatan</a>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-sm align-middle mb-0">
                                        <thead><tr><th>Tanggal</th><th>Transaksi</th><th>Alasan</th><th>Status</th><th>Hasil tinjauan</th></tr></thead>
                                        <tbody>
                                            @forelse ($objections as $objection)
                                                <tr>
                                                    <td>{{ $objection->created_at->format('d/m/Y') }}</td>
                                                    <td>{{ $objection->source?->category?->name ?? 'Transaksi poin' }}</td>
                                                    <td>{{ $objection->reason }}</td>
                                                    <td><span class="badge {{ match($objection->status) {'approved' => 'text-bg-success', 'rejected' => 'text-bg-danger', default => 'text-bg-warning'} }}">{{ ucfirst($objection->status) }}</span></td>
                                                    <td>
                                                        {{ $objection->decision_reason ?? 'Menunggu peninjauan guru.' }}
                                                        @if ($objection->correctionTransaction)
                                                            <span class="d-block small text-success">
                                                                Koreksi poin {{ $objection->correctionTransaction->points > 0 ? '+' : '' }}{{ $objection->correctionTransaction->points }}
                                                            </span>
                                                        @endif
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr><td colspan="5" class="text-center text-secondary py-3">Belum ada pengajuan keberatan.</td></tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </section>
                        <div class="table-responsive">
                            <table id="point-history" class="table align-middle">
                                <thead><tr><th>Tanggal</th><th>Catatan</th><th class="text-end">Poin</th></tr></thead>
                                <tbody>
                                    @forelse ($recentTransactions as $transaction)
                                        <tr>
                                            <td class="text-nowrap">{{ $transaction->transaction_date->format('d/m/Y') }}</td>
                                            <td>{{ $transaction->category->name }}<span class="d-block small text-secondary">{{ $transaction->description ?: 'Transaksi poin' }}</span></td>
                                            <td class="text-end fw-bold {{ $transaction->points > 0 ? 'text-success' : 'text-danger' }}">{{ $transaction->points > 0 ? '+' : '' }}{{ $transaction->points }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="3" class="text-center text-secondary py-4">Belum ada transaksi poin.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>
            </div>
            <div class="col-xl-6">
                <section class="card border-0 shadow-sm h-100">
                    <div class="card-body p-3 p-lg-4">
                        <h2 class="h5 fw-bold mb-3">Aktivitas kedisiplinan</h2>
                        <h3 class="h6 fw-semibold">Keterlambatan</h3>
                        @forelse ($latenessHistory as $lateness)
                            <p class="small border-bottom pb-2">{{ $lateness->attendance_date->format('d/m/Y') }} · tiba {{ substr((string) $lateness->arrival_time, 0, 5) }}</p>
                        @empty
                            <p class="small text-secondary">Belum ada catatan keterlambatan.</p>
                        @endforelse

                        <h3 class="h6 fw-semibold mt-3">Kehadiran piket</h3>
                        @forelse ($attendanceHistory as $attendance)
                            <p class="small border-bottom pb-2">{{ $attendance->attended_at->format('d/m/Y H:i') }} · {{ $attendance->schedule->duty_date->format('d/m/Y') }}</p>
                        @empty
                            <p class="small text-secondary">Belum ada catatan kehadiran piket.</p>
                        @endforelse

                        <h3 class="h6 fw-semibold mt-3">Pelanggaran / reward terbaru</h3>
                        @forelse ($violationHistory->merge($rewardHistory)->sortByDesc('transaction_date')->take(6) as $entry)
                            <p class="small border-bottom pb-2 mb-2">
                                {{ $entry->transaction_date->format('d/m/Y') }} · {{ $entry->category->name }}
                                <span class="badge {{ $entry->points < 0 ? 'text-bg-danger' : 'text-bg-success' }}">{{ $entry->points > 0 ? '+' : '' }}{{ $entry->points }}</span>
                            </p>
                        @empty
                            <p class="small text-secondary">Belum ada pelanggaran atau reward.</p>
                        @endforelse
                    </div>
                </section>
            </div>
        </div>
    @endif
</x-app-layout>
