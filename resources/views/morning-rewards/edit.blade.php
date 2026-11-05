<x-app-layout>
    <x-slot name="header">
        <div>
            <a class="small text-decoration-none" href="{{ route('morning-rewards.index') }}">&larr; Riwayat penghargaan pagi</a>
            <h1 class="h3 fw-bold mt-1 mb-0">Edit Penghargaan Pagi</h1>
        </div>
    </x-slot>
    <section class="card border-0 shadow-sm">
        <div class="card-body">
            <div class="alert alert-info">Pengubahan akan membuat transaksi koreksi dan transaksi penghargaan baru pada ledger.</div>
            <form method="POST" action="{{ route('morning-rewards.update', $reward) }}" class="row g-3">
                @csrf @method('PUT')
                <div class="col-12">
                    <label class="form-label" for="student_id">Siswa</label>
                    <select class="form-select select2-student" id="student_id" name="student_id" required>
                        @foreach ($students as $student)
                            <option value="{{ $student->id }}" @selected(old('student_id', $reward->student_id) == $student->id)
                                @disabled(!$student->is_active && $student->id !== $reward->student_id)>
                                {{ $student->user->name }} — {{ $student->nis }} — {{ $student->class_name }}{{ $student->is_active ? '' : ' — Nonaktif' }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6"><label class="form-label" for="reward_date">Tanggal</label><input class="form-control" type="date" id="reward_date" name="reward_date" max="{{ today()->toDateString() }}" value="{{ old('reward_date', $reward->reward_date->toDateString()) }}" required></div>
                <div class="col-md-3"><label class="form-label" for="arrival_time">Waktu tiba WIB</label><input class="form-control" type="time" min="04:00" max="05:30" id="arrival_time" name="arrival_time" value="{{ old('arrival_time', substr((string) $reward->arrival_time, 0, 5)) }}" required></div>
                <div class="col-md-3"><label class="form-label" for="rank">Peringkat 1–10</label><input class="form-control" type="number" min="1" max="10" id="rank" name="rank" value="{{ old('rank', $reward->rank) }}" required></div>
                <div class="col-12 d-flex justify-content-end gap-2"><a class="btn btn-outline-secondary" href="{{ route('morning-rewards.index') }}">Batal</a><button class="btn btn-primary">Simpan Perubahan</button></div>
            </form>
        </div>
    </section>
</x-app-layout>
