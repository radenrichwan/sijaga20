<x-app-layout>
    <x-slot name="header">
        <div>
            <a class="small text-decoration-none" href="{{ route('lateness.index') }}">&larr; Riwayat keterlambatan</a>
            <h1 class="h3 fw-bold mt-1 mb-0">Catat Keterlambatan</h1>
        </div>
    </x-slot>

    <section class="card border-0 shadow-sm">
        <div class="card-body">
            <form method="POST" action="{{ route('lateness.store') }}" class="row g-3">
                @csrf
                <div class="col-12">
                    @include('lateness.partials.student-options')
                </div>
                @include('lateness.partials.form-fields', ['idPrefix' => 'new-lateness', 'record' => null])
                <div class="col-12 d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <p class="small text-secondary mb-0">Catatan duplikat per siswa per tanggal ditolak. Pengurangan otomatis: 5 poin.</p>
                    <button class="btn btn-warning" type="submit">Simpan Keterlambatan</button>
                </div>
            </form>
        </div>
    </section>
</x-app-layout>
