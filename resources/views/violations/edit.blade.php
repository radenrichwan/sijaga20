<x-app-layout>
    <x-slot name="header">
        <div>
            <a class="small text-decoration-none" href="{{ route('violations.index') }}">&larr; Riwayat pelanggaran</a>
            <h1 class="h3 fw-bold mt-1 mb-0">Edit Pelanggaran</h1>
        </div>
    </x-slot>

    <section class="card border-0 shadow-sm">
        <div class="card-body">
            <div class="alert alert-info">
                {{ $violation->student->user->name }} · NIS {{ $violation->student->nis }} · {{ $violation->student->class_name }}
                <div class="small mt-1">Pengubahan akan membuat transaksi pembatalan dan transaksi pelanggaran baru pada ledger.</div>
            </div>
            <form method="POST" action="{{ route('violations.update', $violation) }}" class="row g-3">
                @csrf
                @method('PUT')
                <div class="col-md-6">
                    @include('violations.partials.category-options', [
                        'idPrefix' => 'edit-violation',
                        'selectedCategory' => $violation->violation_category_id,
                    ])
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="violation_date">Tanggal pelanggaran</label>
                    <input class="form-control" id="violation_date" name="violation_date" type="date"
                        max="{{ today()->toDateString() }}" value="{{ old('violation_date', $violation->violation_date->toDateString()) }}" required>
                </div>
                <div class="col-12">
                    <label class="form-label" for="description">Catatan</label>
                    <textarea class="form-control" id="description" name="description" rows="3" maxlength="2000">{{ old('description', $violation->description) }}</textarea>
                </div>
                <div class="col-12 d-flex justify-content-end gap-2">
                    <a class="btn btn-outline-secondary" href="{{ route('violations.index') }}">Batal</a>
                    <button class="btn btn-primary" type="submit">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </section>
</x-app-layout>
