@php
    $inputName = $inputName ?? 'violation_category_id';
@endphp
<label class="form-label" for="{{ $idPrefix }}-search">Jenis pelanggaran</label>
<input class="form-control mb-2" id="{{ $idPrefix }}-search" type="search" data-category-search="{{ $idPrefix }}-category"
    placeholder="Cari kategori (mis. kaos)" aria-label="Cari jenis pelanggaran">
<select class="form-select" id="{{ $idPrefix }}-category" name="{{ $inputName }}" required>
    <option value="">Pilih kategori pelanggaran</option>
    @foreach ($categories as $category)
        <option value="{{ $category->id }}"
            data-search-alias="{{ $category->name === 'Kospen' ? 'kaos kospen' : $category->name }}"
            @selected(($selectedCategory ?? null) == $category->id)>
            {{ $category->name }} — pengurangan 5 poin
        </option>
    @endforeach
</select>
