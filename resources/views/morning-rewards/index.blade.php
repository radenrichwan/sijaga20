<x-app-layout>
    <x-slot name="header">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
                <p class="text-uppercase small fw-semibold text-success mb-1">04.00–05.30 WIB · +15 poin</p>
                <h1 class="h3 fw-bold mb-0">Penghargaan Pagi</h1>
            </div>
            <a class="btn btn-success" href="{{ route('dashboard') }}#morning-award">Catat Penghargaan</a>
        </div>
    </x-slot>

    <section class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('morning-rewards.index') }}" class="row g-3 align-items-end">
                <div class="col-12 col-md-4"><label class="form-label" for="search">Cari siswa</label><input class="form-control" id="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Nama, NIS, atau NISN"></div>
                <div class="col-6 col-md-2"><label class="form-label" for="month">Bulan</label><input class="form-control" id="month" name="month" type="month" value="{{ $filters['month'] ?? '' }}"></div>
                <div class="col-6 col-md-2">
                    <label class="form-label" for="class_name">Kelas</label>
                    <select class="form-select" id="class_name" name="class_name"><option value="">Semua kelas</option>
                        @foreach ($classes as $className)<option value="{{ $className }}" @selected(($filters['class_name'] ?? '') === $className)>{{ $className }}</option>@endforeach
                    </select>
                </div>
                <div class="col-6 col-md-2"><label class="form-label" for="start_date">Dari tanggal</label><input class="form-control" id="start_date" name="start_date" type="date" value="{{ $filters['start_date'] ?? '' }}"></div>
                <div class="col-6 col-md-2"><label class="form-label" for="end_date">Sampai tanggal</label><input class="form-control" id="end_date" name="end_date" type="date" value="{{ $filters['end_date'] ?? '' }}"></div>
                <div class="col-12 d-flex gap-2"><button class="btn btn-outline-primary">Terapkan Filter</button><a class="btn btn-outline-secondary" href="{{ route('morning-rewards.index') }}">Reset</a></div>
            </form>
        </div>
    </section>

    <section class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light"><tr><th>Tanggal</th><th>Peringkat</th><th>Waktu tiba</th><th>Siswa</th><th>Kelas</th><th>Pencatat</th><th class="text-end">Poin</th><th></th></tr></thead>
                <tbody>
                    @forelse ($rewards as $reward)
                        <tr>
                            <td>{{ $reward->reward_date->format('d/m/Y') }}</td><td>{{ $reward->rank }}</td><td>{{ substr((string) $reward->arrival_time, 0, 5) }} WIB</td>
                            <td>{{ $reward->student->user->name }}<span class="d-block small text-secondary">NIS {{ $reward->student->nis }}</span></td><td>{{ $reward->student->class_name }}</td><td>{{ $reward->recordedBy->name }}</td>
                            <td class="text-end"><span class="badge text-bg-success">+15</span></td>
                            <td class="text-end text-nowrap">
                                <a class="btn btn-sm btn-outline-primary" href="{{ route('morning-rewards.edit', $reward) }}">Edit</a>
                                <form class="d-inline" method="POST" action="{{ route('morning-rewards.destroy', $reward) }}" onsubmit="return confirm('Hapus penghargaan ini? Ledger dikoreksi dengan transaksi baru.')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger" type="submit">Hapus</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td class="text-center text-secondary py-5" colspan="8">Belum ada penghargaan pagi untuk filter ini.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer bg-white">{{ $rewards->links() }}</div>
    </section>
</x-app-layout>
