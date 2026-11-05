<x-app-layout>
    <x-slot name="header">
        <div>
            <a class="small text-decoration-none" href="{{ route('additional-points.index') }}">&larr; Riwayat poin tambahan</a>
            <h1 class="h3 fw-bold mt-1 mb-0">Edit Poin Tambahan</h1>
        </div>
    </x-slot>

    <section class="card border-0 shadow-sm">
        <div class="card-body">
            <div class="alert alert-info">Perubahan akan membuat transaksi koreksi dan transaksi baru pada ledger; histori sebelumnya tetap tersedia.</div>
            <form method="POST" action="{{ route('additional-points.update', $point) }}" class="row g-3">
                @csrf
                @method('PUT')
                @include('additional-points.partials.form-fields', ['idPrefix' => 'edit-additional-point', 'point' => $point])
                <div class="col-12 d-flex justify-content-end gap-2">
                    <a class="btn btn-outline-secondary" href="{{ route('additional-points.index') }}">Batal</a>
                    <button class="btn btn-primary" type="submit">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </section>
</x-app-layout>
