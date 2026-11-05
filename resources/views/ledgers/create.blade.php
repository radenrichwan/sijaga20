<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <div>
                <a href="{{ route('students.index') }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-800">&larr; Daftar siswa</a>
                <h2 class="mt-1 text-xl font-semibold leading-tight text-gray-800">Catat transaksi poin</h2>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
            <div class="rounded-lg bg-white p-6 shadow-sm sm:p-8">
                <p class="mb-6 text-sm text-gray-600">Poin akan dihitung otomatis dari jenis kategori: pelanggaran mengurangi saldo, sedangkan reward menambah saldo.</p>
                @if ($errors->any())
                    <div class="mb-5 rounded-md bg-red-50 p-4 text-sm text-red-800">
                        <p class="font-semibold">Transaksi belum disimpan.</p>
                        <ul class="mt-1 list-inside list-disc">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
                @if ($students->isEmpty() || $categories->isEmpty())
                    <div class="rounded-md bg-yellow-50 p-4 text-sm text-yellow-800">
                        Tambahkan setidaknya satu siswa dan satu kategori sebelum mencatat transaksi.
                    </div>
                @endif

                <form method="POST" action="{{ route('ledgers.store') }}" class="space-y-5">
                    @csrf
                    <div>
                        <label for="student_id" class="mb-1 block text-sm font-medium text-gray-700">Siswa</label>
                        <select id="student_id" name="student_id" required
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">Pilih siswa</option>
                            @foreach ($students as $student)
                                <option value="{{ $student->id }}" @selected((string) old('student_id', $selectedStudent ?: '') === (string) $student->id)>
                                    {{ $student->user->name }} — {{ $student->nisn }} ({{ $student->class_name }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="category_id" class="mb-1 block text-sm font-medium text-gray-700">Pelanggaran / reward</label>
                        <select id="category_id" name="category_id" required
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">Pilih kategori</option>
                            @foreach ($categories as $category)
                                <option value="{{ $category->id }}" @selected((string) old('category_id') === (string) $category->id)>
                                    {{ $category->type === 'violation' ? 'Pelanggaran' : 'Reward' }} — {{ $category->name }} ({{ $category->type === 'violation' ? '-' : '+' }}{{ $category->points }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="transaction_date" class="mb-1 block text-sm font-medium text-gray-700">Tanggal transaksi</label>
                        <input id="transaction_date" name="transaction_date" type="date" required
                            value="{{ old('transaction_date', now()->toDateString()) }}"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label for="description" class="mb-1 block text-sm font-medium text-gray-700">Catatan (opsional)</label>
                        <textarea id="description" name="description" rows="4" maxlength="5000"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('description') }}</textarea>
                    </div>
                    <div class="flex justify-end gap-3">
                        <a href="{{ route('students.index') }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Batal</a>
                        <button type="submit" @disabled($students->isEmpty() || $categories->isEmpty())
                            class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-50">Simpan transaksi</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
