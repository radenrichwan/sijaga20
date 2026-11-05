<x-app-layout>
    <x-slot name="header">
        <div>
            <a class="small text-decoration-none" href="{{ route('additional-points.index') }}">&larr; Riwayat poin tambahan</a>
            <h1 class="h3 fw-bold mt-1 mb-0">Tambah Poin Siswa</h1>
        </div>
    </x-slot>

    <section class="card border-0 shadow-sm">
        <div class="card-body">
            <form method="POST" action="{{ route('additional-points.store') }}" class="row g-3">
                @csrf
                @include('additional-points.partials.form-fields', ['idPrefix' => 'new-additional-point', 'point' => null])
                <div class="col-12 d-flex justify-content-between align-items-center gap-2">
                    <span class="small text-secondary">Jumlah harus bilangan bulat positif.</span>
                    <button class="btn btn-success" type="submit">Simpan Poin</button>
                </div>
            </form>
        </div>
    </section>
</x-app-layout>
