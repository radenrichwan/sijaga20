<x-app-layout>
    <x-slot name="header">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
                @if (auth()->user()->isGuru())
                    <a class="small text-decoration-none" href="{{ route('students.index') }}">&larr; Daftar siswa</a>
                @else
                    <a class="small text-decoration-none" href="{{ route('dashboard') }}">&larr; Dashboard pribadi</a>
                @endif
                <h1 class="h3 fw-bold mt-1 mb-0">Profil Siswa</h1>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a class="btn btn-outline-primary" href="{{ route('students.transcript', $student) }}">
                    <i class="bi bi-file-earmark-pdf me-1" aria-hidden="true"></i>Cetak Transkrip Poin
                </a>
                @if (auth()->user()->isGuru())
                    <a class="btn btn-primary" href="{{ route('ledgers.create', ['student_id' => $student->id]) }}">Catat poin</a>
                @endif
            </div>
        </div>
    </x-slot>

    <div class="row g-3 mb-4">
        <div class="col-md-6 col-xl-3"><article class="card h-100 border-0 shadow-sm"><div class="card-body"><p class="small text-secondary mb-1">Nama lengkap</p><p class="fw-semibold mb-0">{{ $student->user->name }}</p></div></article></div>
        <div class="col-md-6 col-xl-3"><article class="card h-100 border-0 shadow-sm"><div class="card-body"><p class="small text-secondary mb-1">NIS / username</p><p class="fw-semibold mb-0">{{ $student->nis }}</p></div></article></div>
        <div class="col-md-6 col-xl-3"><article class="card h-100 border-0 shadow-sm"><div class="card-body"><p class="small text-secondary mb-1">NISN</p><p class="fw-semibold mb-0">{{ $student->nisn }}</p></div></article></div>
        <div class="col-md-6 col-xl-3"><article class="card h-100 border-0 shadow-sm"><div class="card-body"><p class="small text-secondary mb-1">Kelas / Jurusan</p><p class="fw-semibold mb-0">{{ $student->class_name }} · {{ $student->major }}</p></div></article></div>
        <div class="col-12">
            <article class="card border-0 shadow-sm">
                <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3">
                    <div>
                        @php
                            $pointBadge = $student->current_points >= 100 ? 'success' : ($student->current_points >= 75 ? 'warning' : 'danger');
                        @endphp
                        <p class="small text-secondary mb-1">Total akumulasi poin saat ini</p>
                        <span class="badge text-bg-{{ $pointBadge }} fs-5">{{ number_format($student->current_points) }} poin</span>
                    </div>
                    <div class="text-md-end"><p class="small text-secondary mb-1">Jenis kelamin · Tingkat</p><p class="fw-semibold mb-0">{{ $student->gender }} · {{ $student->grade }}</p></div>
                </div>
            </article>
        </div>
    </div>

    <section class="card border-0 shadow-sm">
        <div class="card-header bg-white py-3"><h2 class="h5 fw-bold mb-0">Riwayat transaksi poin</h2></div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr><th>Tanggal</th><th>Kategori</th><th>Catatan</th><th>Pencatat</th><th class="text-end">Poin</th></tr>
                </thead>
                <tbody>
                    @forelse ($ledgers as $ledger)
                        <tr>
                            <td>{{ $ledger->transaction_date->format('d/m/Y') }}</td>
                            <td>{{ $ledger->category->name }}</td>
                            <td>{{ $ledger->description ?: '—' }}</td>
                            <td>{{ $ledger->recordedBy->name }}</td>
                            <td class="text-end"><span class="badge text-bg-{{ $ledger->points >= 0 ? 'success' : 'danger' }}">{{ $ledger->points > 0 ? '+' : '' }}{{ $ledger->points }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-secondary py-5">Belum ada transaksi poin.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer bg-white">{{ $ledgers->links() }}</div>
    </section>
</x-app-layout>
