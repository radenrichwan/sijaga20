<x-app-layout>
    <x-slot name="header">
        <div>
            <a class="small text-decoration-none" href="{{ route('violations.index') }}">&larr; Riwayat pelanggaran</a>
            <h1 class="h3 fw-bold mt-1 mb-0">Catat Pelanggaran</h1>
        </div>
    </x-slot>

    <section class="card border-0 shadow-sm">
        <div class="card-body">
            <form method="POST" action="{{ route('violations.store') }}" class="row g-3">
                @csrf
                <div class="col-md-6">
                    @include('violations.partials.student-options')
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="violation_date">Tanggal pelanggaran</label>
                    <input class="form-control" id="violation_date" name="violation_date" type="date"
                        max="{{ today()->toDateString() }}" value="{{ old('violation_date', today()->toDateString()) }}" required>
                </div>

                @php
                    $selectedCategories = old('violation_category_ids', [old('violation_category_id')]);
                    $selectedCategories = is_array($selectedCategories) && count($selectedCategories) ? $selectedCategories : [''];
                @endphp
                <div class="col-12">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                        <h2 class="h6 fw-bold mb-0">Rincian pelanggaran</h2>
                        <button class="btn btn-sm btn-outline-primary" type="button" data-add-violation>
                            <i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Tambah Pelanggaran
                        </button>
                    </div>
                    <div id="violation-category-rows" data-next-index="{{ count($selectedCategories) }}">
                        @foreach ($selectedCategories as $index => $selectedCategory)
                            <div class="row g-2 align-items-end mb-3" data-violation-row>
                                <div class="col">
                                    @include('violations.partials.category-options', [
                                        'idPrefix' => 'new-violation-'.$index,
                                        'inputName' => 'violation_category_ids[]',
                                        'selectedCategory' => $selectedCategory,
                                    ])
                                </div>
                                <div class="col-auto">
                                    <button class="btn btn-outline-danger" type="button" data-remove-violation aria-label="Hapus pelanggaran">
                                        <i class="bi bi-trash" aria-hidden="true"></i>
                                    </button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <p class="small text-secondary mb-0">Setiap jenis pelanggaran mengurangi saldo sebesar 5 poin. Jenis yang sama dapat ditambahkan lebih dari sekali.</p>
                </div>

                <div class="col-12">
                    <label class="form-label" for="description">Catatan</label>
                    <textarea class="form-control" id="description" name="description" rows="3" maxlength="2000">{{ old('description') }}</textarea>
                </div>
                <div class="col-12 d-flex flex-wrap justify-content-end align-items-center gap-2">
                    <button class="btn btn-primary" type="submit">Simpan Pelanggaran</button>
                </div>
            </form>

            <template id="violation-category-template">
                <div class="row g-2 align-items-end mb-3" data-violation-row>
                    <div class="col">
                        @include('violations.partials.category-options', [
                            'idPrefix' => 'new-violation-__INDEX__',
                            'inputName' => 'violation_category_ids[]',
                            'selectedCategory' => null,
                        ])
                    </div>
                    <div class="col-auto">
                        <button class="btn btn-outline-danger" type="button" data-remove-violation aria-label="Hapus pelanggaran">
                            <i class="bi bi-trash" aria-hidden="true"></i>
                        </button>
                    </div>
                </div>
            </template>
        </div>
    </section>
</x-app-layout>
