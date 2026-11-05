<x-app-layout>
    <x-slot name="header">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <h1 class="h3 fw-bold mb-1">Penggantian Anggota Piket</h1>
                <p class="text-secondary mb-0">Anggota pengganti harus akun operasional aktif. Pengajuan hari-H hanya sebelum pukul 06.00 WIB.</p>
            </div>
            <a class="btn btn-outline-primary" href="{{ route('duty-schedules.index') }}">Jadwal piket</a>
        </div>
    </x-slot>

    @if ($schedules->isNotEmpty())
        <section class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3"><h2 class="h5 fw-bold mb-0">Ajukan Penggantian</h2></div>
            <div class="card-body">
                @foreach ($schedules as $schedule)
                    @if ($schedule->members->isNotEmpty() && ($isScheduleManager || $schedule->members->contains('id', auth()->id())))
                        <form method="POST" action="{{ route('duty-replacements.store') }}" class="row g-3 align-items-end border-bottom py-3">
                            @csrf
                            <input type="hidden" name="duty_schedule_id" value="{{ $schedule->id }}">
                            <div class="col-md-3">
                                <label class="form-label">Tanggal</label>
                                <input class="form-control" value="{{ $schedule->duty_date->translatedFormat('l, d M Y') }}" disabled>
                            </div>
                            <div class="col-md-3">
                                <label for="original-{{ $schedule->id }}" class="form-label">Anggota berhalangan</label>
                                @if ($isScheduleManager)
                                    <select class="form-select" id="original-{{ $schedule->id }}" name="original_user_id" required>
                                        @foreach ($schedule->members as $member)
                                            <option value="{{ $member->id }}">{{ $member->name }} ({{ $member->username }})</option>
                                        @endforeach
                                    </select>
                                @else
                                    <input type="hidden" name="original_user_id" value="{{ auth()->id() }}">
                                    <input class="form-control" id="original-{{ $schedule->id }}" value="{{ auth()->user()->name }}" disabled>
                                @endif
                            </div>
                            <div class="col-md-3">
                                <label for="replacement-{{ $schedule->id }}" class="form-label">Pengganti</label>
                                <select class="form-select" id="replacement-{{ $schedule->id }}" name="replacement_user_id" required>
                                    @foreach ($operationalUsers->reject(fn ($member) => $schedule->members->contains('id', $member->id)) as $member)
                                        <option value="{{ $member->id }}">{{ $member->name }} ({{ $member->username }})</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="reason-{{ $schedule->id }}" class="form-label">Alasan</label>
                                <input class="form-control" id="reason-{{ $schedule->id }}" name="reason" maxlength="1000" required>
                            </div>
                            <div class="col-md-3">
                                <label for="replacement-student-{{ $schedule->id }}" class="form-label">Akun siswa penerima poin</label>
                                <select class="form-select" id="replacement-student-{{ $schedule->id }}" name="replacement_student_id" required>
                                    <option value="">Pilih siswa</option>
                                    @foreach ($activeStudents as $student)
                                        <option value="{{ $student->id }}">{{ $student->user->name }} ({{ $student->nis }})</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-12 text-end">
                                <button class="btn btn-primary" type="submit">Ajukan penggantian</button>
                            </div>
                        </form>
                    @endif
                @endforeach
            </div>
        </section>
    @endif

    <section class="card border-0 shadow-sm">
        <div class="card-header bg-white py-3"><h2 class="h5 fw-bold mb-0">Riwayat dan Persetujuan</h2></div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead><tr><th>Jadwal</th><th>Anggota awal</th><th>Pengganti</th><th>Akun siswa penerima</th><th>Alasan</th><th>Status</th><th>Keputusan</th></tr></thead>
                <tbody>
                    @forelse ($replacements as $replacement)
                        <tr>
                            <td>{{ $replacement->schedule->duty_date->translatedFormat('d M Y') }}</td>
                            <td>{{ $replacement->originalUser->name }}</td>
                            <td>{{ $replacement->replacementUser->name }}</td>
                            <td>{{ $replacement->replacementStudent?->user?->name ?? '—' }} ({{ $replacement->replacementStudent?->nis ?? '—' }})</td>
                            <td>{{ $replacement->reason }}</td>
                            <td><span class="badge {{ match($replacement->status) {'approved' => 'text-bg-success', 'rejected' => 'text-bg-danger', default => 'text-bg-warning'} }}">{{ ucfirst($replacement->status) }}</span></td>
                            <td>
                                @if ($isScheduleManager && $replacement->status === 'pending')
                                    <div class="d-flex flex-wrap gap-2">
                                        <form method="POST" action="{{ route('duty-replacements.decide', $replacement) }}">
                                            @csrf @method('PATCH')
                                            <input type="hidden" name="status" value="approved">
                                            <button class="btn btn-sm btn-success" type="submit">Setujui</button>
                                        </form>
                                        <form method="POST" action="{{ route('duty-replacements.decide', $replacement) }}" class="d-flex gap-1">
                                            @csrf @method('PATCH')
                                            <input type="hidden" name="status" value="rejected">
                                            <input class="form-control form-control-sm" name="decision_reason" placeholder="Alasan penolakan" required>
                                            <button class="btn btn-sm btn-outline-danger" type="submit">Tolak</button>
                                        </form>
                                    </div>
                                @elseif ($replacement->decidedBy)
                                    {{ $replacement->decidedBy->name }}
                                    @if ($replacement->rejection_reason)<div class="small text-secondary">{{ $replacement->rejection_reason }}</div>@endif
                                @else
                                    <span class="text-secondary">Menunggu persetujuan</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-secondary py-4">Belum ada pengajuan penggantian.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-body">{{ $replacements->links() }}</div>
    </section>
</x-app-layout>
