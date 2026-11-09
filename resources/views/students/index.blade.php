<x-app-layout>
    <x-slot name="header">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
                <p class="text-uppercase small fw-semibold text-primary mb-1">Master Data</p>
                <h1 class="h3 fw-bold mb-0">Manajemen Data Siswa</h1>
            </div>
            @if (auth()->user()->isGuru())
                <div class="d-flex flex-wrap gap-2">
                    <a class="btn btn-outline-success" href="{{ route('students.export', request()->query()) }}">Export CSV</a>
                    <a class="btn btn-outline-success" href="{{ route('students.export.excel', request()->query()) }}">Export Excel</a>
                    <button class="btn btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#createStudentModal">Tambah Siswa</button>
                </div>
            @endif
        </div>
    </x-slot>

    @if (session('import_errors'))
        <div class="alert alert-warning" role="alert">
            <h2 class="h6 fw-bold">Sebagian baris impor tidak diproses</h2>
            <ul class="mb-0">
                @foreach (session('import_errors') as $importError)
                    <li>Baris {{ $importError['row'] }}: {{ implode(' ', $importError['messages']) }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <section class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('students.index') }}" class="row g-3 align-items-end">
                <div class="col-12 col-lg-4">
                    <label class="form-label" for="student-search">Cari siswa</label>
                    <input class="form-control" id="student-search" name="search" type="search" value="{{ $filters['search'] ?? '' }}"
                        placeholder="Nama, NIS, atau NISN">
                </div>
                <div class="col-6 col-lg-2">
                    <label class="form-label" for="filter-grade">Tingkat</label>
                    <select class="form-select" id="filter-grade" name="grade">
                        <option value="">Semua tingkat</option>
                        @foreach (['X', 'XI', 'XII'] as $grade)
                            <option value="{{ $grade }}" @selected(($filters['grade'] ?? '') === $grade)>{{ $grade }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-lg-3">
                    <label class="form-label" for="filter-major">Jurusan</label>
                    <select class="form-select" id="filter-major" name="major">
                        <option value="">Semua jurusan</option>
                        @foreach ($majors as $major)
                            <option value="{{ $major }}" @selected(($filters['major'] ?? '') === $major)>{{ $major }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-lg-2">
                    <label class="form-label" for="filter-status">Status akun</label>
                    <select class="form-select" id="filter-status" name="status">
                        <option value="">Semua status</option>
                        <option value="active" @selected(($filters['status'] ?? '') === 'active')>Aktif</option>
                        <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>Nonaktif</option>
                    </select>
                </div>
                <div class="col-6 col-lg-1 d-grid">
                    <button class="btn btn-outline-primary" type="submit">Filter</button>
                </div>
            </form>
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 border-top mt-3 pt-3">
                <p class="small text-secondary mb-0">Impor CSV atau Excel (.xlsx). Kata sandi awal akun baru adalah NIS siswa.</p>
                @if (auth()->user()->isGuru())
                    <form action="{{ route('students.import') }}" method="POST" enctype="multipart/form-data" class="d-flex flex-column gap-2">
                        @csrf
                        <input class="form-control" type="file" name="file" accept=".csv,.txt,.xlsx" required aria-label="Pilih file data siswa">
                        <div class="d-flex flex-wrap justify-content-end gap-2">
                            <button class="btn btn-primary btn-sm text-nowrap" type="submit">Import Data Siswa</button>
                            <a class="btn btn-success btn-sm text-nowrap" href="{{ route('students.import.template') }}">Unduh Template Import</a>
                        </div>
                    </form>
                @endif
            </div>
        </div>
    </section>

    <section class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th scope="col">NIS / NISN</th>
                        <th scope="col">Nama lengkap</th>
                        <th scope="col">Kelas / Jurusan</th>
                        <th scope="col">Poin</th>
                        <th scope="col">Status</th>
                        <th scope="col" class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($students as $student)
                        <tr>
                            <td>
                                <span class="fw-semibold d-block">{{ $student->nis }}</span>
                                <span class="small text-secondary">{{ $student->nisn }}</span>
                            </td>
                            <td>{{ $student->user->name }}</td>
                            <td>
                                <span class="d-block">{{ $student->class_name }}</span>
                                <span class="small text-secondary">{{ $student->major }} · {{ $student->grade }}</span>
                            </td>
                            <td>
                                @php
                                    $pointBadge = $student->current_points >= 100 ? 'success' : ($student->current_points >= 75 ? 'warning' : 'danger');
                                @endphp
                                <span class="badge text-bg-{{ $pointBadge }}">{{ number_format($student->current_points) }} poin</span>
                            </td>
                            <td>
                                <span class="badge text-bg-{{ $student->is_active ? 'success' : 'secondary' }}">
                                    {{ $student->is_active ? 'Aktif' : 'Nonaktif' }}
                                </span>
                            </td>
                            <td class="text-end text-nowrap">
                                <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="modal" data-bs-target="#studentDetail{{ $student->id }}">Detail</button>
                                @if (auth()->user()->isGuru())
                                    <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="modal" data-bs-target="#editStudent{{ $student->id }}">Edit</button>
                                    <form class="d-inline" method="POST" action="{{ route('students.status', $student) }}">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="is_active" value="{{ $student->is_active ? '0' : '1' }}">
                                        <button class="btn btn-sm btn-outline-{{ $student->is_active ? 'danger' : 'success' }}" type="submit">
                                            {{ $student->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                                        </button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                        @if (auth()->user()->isGuru())
                            @include('students.partials.edit-modal', ['student' => $student])
                        @endif
                        @include('students.partials.detail-modal', ['student' => $student])
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-secondary py-5">Tidak ada data siswa yang sesuai dengan filter.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer bg-white d-flex flex-wrap justify-content-between align-items-center gap-2">
            <span class="small text-secondary">Menampilkan {{ $students->firstItem() ?? 0 }}–{{ $students->lastItem() ?? 0 }} dari {{ $students->total() }} siswa</span>
            {{ $students->links() }}
        </div>
    </section>

    @include('students.partials.create-modal')
</x-app-layout>
