<div class="mb-3">
    <label for="name-{{ $category?->id ?? 'new' }}" class="form-label">Nama kategori</label>
    <input id="name-{{ $category?->id ?? 'new' }}" name="name" type="text" maxlength="255" required
        value="{{ $category ? (old('category_id') == $category->id ? old('name', $category->name) : $category->name) : old('name') }}"
        class="form-control">
</div>
<div class="mb-3">
    <label for="type-{{ $category?->id ?? 'new' }}" class="form-label">Jenis</label>
    <select id="type-{{ $category?->id ?? 'new' }}" name="type" required class="form-select">
        <option value="">Pilih jenis</option>
        <option value="violation" @selected(($category && old('category_id') != $category->id ? $category->type : old('type', $category?->type)) === 'violation')>Pelanggaran (poin berkurang)</option>
        <option value="reward" @selected(($category && old('category_id') != $category->id ? $category->type : old('type', $category?->type)) === 'reward')>Penghargaan (poin bertambah)</option>
    </select>
</div>
<div class="mb-3">
    <label for="points-{{ $category?->id ?? 'new' }}" class="form-label">Nilai poin positif</label>
    <input id="points-{{ $category?->id ?? 'new' }}" name="points" type="number" min="1" max="2147483647" required
        value="{{ $category ? (old('category_id') == $category->id ? old('points', $category->points) : $category->points) : old('points') }}"
        class="form-control">
    <div class="form-text">Tanda transaksi ditentukan otomatis berdasarkan jenis kategori.</div>
</div>
<div>
    <label for="description-{{ $category?->id ?? 'new' }}" class="form-label">Deskripsi (opsional)</label>
    <textarea id="description-{{ $category?->id ?? 'new' }}" name="description" rows="3" class="form-control">{{ $category ? (old('category_id') == $category->id ? old('description', $category->description) : $category->description) : old('description') }}</textarea>
</div>
