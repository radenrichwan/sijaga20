<x-app-layout>
    <x-slot name="header">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div>
                <p class="text-uppercase small fw-semibold text-primary mb-1">Master Data</p>
                <h1 class="h3 fw-bold mb-0">Kategori Poin</h1>
            </div>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createCategoryModal">
                <i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Tambah Kategori
            </button>
        </div>
    </x-slot>

    @if ($errors->any())
        <div class="alert alert-danger" role="alert">
            <strong>Periksa kembali data kategori.</strong>
            <ul class="mb-0 mt-2">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <section class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Kategori</th><th>Jenis</th><th>Nilai poin</th><th>Deskripsi</th><th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($categories as $category)
                        <tr>
                            <td class="fw-semibold">{{ $category->name }}</td>
                            <td><span class="badge text-bg-{{ $category->type === 'reward' ? 'success' : 'danger' }}">{{ $category->type === 'reward' ? 'Penghargaan' : 'Pelanggaran' }}</span></td>
                            <td>{{ $category->type === 'reward' ? '+' : '-' }}{{ abs($category->points) }}</td>
                            <td>{{ $category->description ?: '—' }}</td>
                            <td class="text-end text-nowrap">
                                <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editCategory{{ $category->id }}">Edit</button>
                                <form method="POST" action="{{ route('categories.destroy', $category) }}" class="d-inline"
                                    onsubmit="return confirm('Hapus kategori ini? Kategori yang sudah dipakai tidak dapat dihapus.')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger">Hapus</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-secondary py-5">Belum ada kategori poin.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer bg-white">{{ $categories->links() }}</div>
    </section>

    @foreach ($categories as $category)
        <div class="modal fade" id="editCategory{{ $category->id }}" tabindex="-1" aria-labelledby="editCategoryTitle{{ $category->id }}" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2 class="modal-title fs-5" id="editCategoryTitle{{ $category->id }}">Edit Kategori</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                    </div>
                    <form method="POST" action="{{ route('categories.update', $category) }}" data-reset-on-close>
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="category_id" value="{{ $category->id }}">
                        <div class="modal-body">@include('categories.partials.fields', ['category' => $category])</div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                            <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endforeach

    <div class="modal fade" id="createCategoryModal" tabindex="-1" aria-labelledby="createCategoryTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title fs-5" id="createCategoryTitle">Tambah Kategori</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <form method="POST" action="{{ route('categories.store') }}" data-reset-on-close>
                    @csrf
                    <div class="modal-body">@include('categories.partials.fields', ['category' => null])</div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">Simpan Kategori</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @if ($errors->any())
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const categoryId = @json(old('category_id'));
                const modal = categoryId ? document.getElementById(`editCategory${categoryId}`) : document.getElementById('createCategoryModal');

                if (modal) {
                    bootstrap.Modal.getOrCreateInstance(modal).show();
                }
            });
        </script>
    @endif
</x-app-layout>
