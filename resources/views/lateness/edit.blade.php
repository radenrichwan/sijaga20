<x-app-layout>
    <x-slot name="header">
        <div>
            <a class="small text-decoration-none" href="{{ route('lateness.index') }}">&larr; Riwayat keterlambatan</a>
            <h1 class="h3 fw-bold mt-1 mb-0">Edit Keterlambatan</h1>
        </div>
    </x-slot>

    <section class="card border-0 shadow-sm">
        <div class="card-body">
            <div class="alert alert-info">
                {{ $record->student->user->name }} · NIS {{ $record->student->nis }} · {{ $record->student->class_name }}
                <div class="small mt-1">Pengubahan membuat transaksi pembatalan dan transaksi keterlambatan baru pada ledger.</div>
            </div>
            <form method="POST" action="{{ route('lateness.update', $record) }}" class="row g-3">
                @csrf
                @method('PUT')
                @include('lateness.partials.form-fields', ['idPrefix' => 'edit-lateness', 'record' => $record])
                <div class="col-12 d-flex justify-content-end gap-2">
                    <a class="btn btn-outline-secondary" href="{{ route('lateness.index') }}">Batal</a>
                    <button class="btn btn-primary" type="submit">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </section>
</x-app-layout>
