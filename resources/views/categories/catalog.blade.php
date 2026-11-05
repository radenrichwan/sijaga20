<x-app-layout>
    <x-slot name="header">
        <h1 class="h3 fw-bold mb-0">Kategori Poin</h1>
    </x-slot>

    <section class="card border-0 shadow-sm">
        <div class="card-body p-3 p-lg-4">
            <p class="text-secondary">Daftar kategori pelanggaran dan reward yang digunakan untuk pencatatan poin.</p>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead><tr><th>Nama kategori</th><th>Jenis</th><th>Nilai poin</th><th>Deskripsi</th></tr></thead>
                    <tbody>
                        @forelse ($categories as $category)
                            <tr>
                                <td class="fw-semibold">{{ $category->name }}</td>
                                <td><span class="badge {{ $category->type === 'reward' ? 'text-bg-success' : 'text-bg-danger' }}">{{ $category->type === 'reward' ? 'Reward' : 'Pelanggaran' }}</span></td>
                                <td>{{ $category->type === 'reward' ? '+' : '-' }}{{ abs($category->points) }}</td>
                                <td>{{ $category->description ?: '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-secondary py-4">Belum ada kategori.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </section>
</x-app-layout>
