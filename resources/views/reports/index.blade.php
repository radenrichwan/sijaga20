<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">Laporan Poin Siswa</h2>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl space-y-5 px-4 sm:px-6 lg:px-8">
            @if ($errors->any())
                <div class="rounded-md bg-red-50 p-4 text-sm text-red-800">
                    <ul class="list-inside list-disc">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                </div>
            @endif

            <form method="GET" action="{{ route('reports.index') }}" class="grid gap-4 rounded-lg bg-white p-5 shadow-sm sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <label for="class_name" class="mb-1 block text-sm font-medium text-gray-700">Kelas</label>
                    <select id="class_name" name="class_name" class="w-full rounded-md border-gray-300 text-sm shadow-sm">
                        <option value="">Semua kelas</option>
                        @foreach ($classes as $className)
                            <option value="{{ $className }}" @selected(($filters['class_name'] ?? '') === $className)>{{ $className }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="major" class="mb-1 block text-sm font-medium text-gray-700">Jurusan</label>
                    <select id="major" name="major" class="w-full rounded-md border-gray-300 text-sm shadow-sm">
                        <option value="">Semua jurusan</option>
                        @foreach ($majors as $major)
                            <option value="{{ $major }}" @selected(($filters['major'] ?? '') === $major)>{{ $major }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="month" class="mb-1 block text-sm font-medium text-gray-700">Bulan</label>
                    <select id="month" name="month" class="w-full rounded-md border-gray-300 text-sm shadow-sm">
                        <option value="">Semua bulan</option>
                        @foreach (range(1, 12) as $month)
                            <option value="{{ $month }}" @selected((int) ($filters['month'] ?? 0) === $month)>{{ \Carbon\Carbon::create()->month($month)->translatedFormat('F') }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="year" class="mb-1 block text-sm font-medium text-gray-700">Tahun</label>
                    <input id="year" name="year" type="number" min="2000" max="2100" value="{{ $filters['year'] ?? '' }}" class="w-full rounded-md border-gray-300 text-sm shadow-sm">
                </div>
                <div>
                    <label for="semester" class="mb-1 block text-sm font-medium text-gray-700">Semester</label>
                    <select id="semester" name="semester" class="w-full rounded-md border-gray-300 text-sm shadow-sm">
                        <option value="">Semua semester</option>
                        <option value="1" @selected(($filters['semester'] ?? '') === '1')>Semester 1 (Juli–Desember)</option>
                        <option value="2" @selected(($filters['semester'] ?? '') === '2')>Semester 2 (Januari–Juni)</option>
                    </select>
                </div>
                <div>
                    <label for="category_id" class="mb-1 block text-sm font-medium text-gray-700">Kategori poin</label>
                    <select id="category_id" name="category_id" class="w-full rounded-md border-gray-300 text-sm shadow-sm">
                        <option value="">Semua kategori</option>
                        @foreach ($categoryOptions as $category)
                            <option value="{{ $category->id }}" @selected((string) ($filters['category_id'] ?? '') === (string) $category->id)>{{ $category->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="type" class="mb-1 block text-sm font-medium text-gray-700">Jenis transaksi</label>
                    <select id="type" name="type" class="w-full rounded-md border-gray-300 text-sm shadow-sm">
                        <option value="">Semua jenis</option>
                        <option value="violation" @selected(($filters['type'] ?? '') === 'violation')>Pelanggaran</option>
                        <option value="reward" @selected(($filters['type'] ?? '') === 'reward')>Reward</option>
                    </select>
                </div>
                <div>
                    <label for="start_date" class="mb-1 block text-sm font-medium text-gray-700">Tanggal mulai</label>
                    <input id="start_date" name="start_date" type="date" value="{{ $filters['start_date'] ?? '' }}" class="w-full rounded-md border-gray-300 text-sm shadow-sm">
                </div>
                <div>
                    <label for="end_date" class="mb-1 block text-sm font-medium text-gray-700">Tanggal selesai</label>
                    <input id="end_date" name="end_date" type="date" value="{{ $filters['end_date'] ?? '' }}" class="w-full rounded-md border-gray-300 text-sm shadow-sm">
                </div>
                <div class="flex items-end gap-2">
                    <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Terapkan</button>
                    <a href="{{ route('reports.index') }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Reset</a>
                </div>
            </form>

            <div class="flex flex-wrap gap-3">
                <a href="{{ route('reports.export.excel', request()->query()) }}" class="rounded-md bg-green-700 px-4 py-2 text-sm font-semibold text-white hover:bg-green-800">Export Excel / CSV</a>
                <a href="{{ route('reports.print', request()->query()) }}" class="rounded-md bg-gray-800 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-900">Unduh PDF</a>
            </div>

            <section class="rounded-lg bg-white p-5 shadow-sm">
                <div class="mb-4 flex flex-wrap justify-between gap-2">
                    <h3 class="font-semibold text-gray-900">Rekap frekuensi dan poin</h3>
                    <span class="text-sm text-gray-600">{{ number_format($totalStudents) }} siswa · Total {{ $totalPoints > 0 ? '+' : '' }}{{ number_format($totalPoints) }} poin</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left font-semibold text-gray-600">Siswa</th>
                                @foreach ($categories as $category)
                                    <th class="px-4 py-3 text-left font-semibold text-gray-600">{{ $category->name }}</th>
                                @endforeach
                                <th class="px-4 py-3 text-left font-semibold text-gray-600">Absensi piket</th>
                                <th class="px-4 py-3 text-right font-semibold text-gray-600">Total poin</th>
                            </tr>
                        </thead>
                        @forelse ($classGroups as $group)
                            <tbody class="divide-y divide-gray-100">
                                <tr class="bg-indigo-50"><th colspan="{{ $categories->count() + 3 }}" class="px-4 py-2 text-left font-semibold text-indigo-900">Kelas {{ $group['name'] }}</th></tr>
                                @foreach ($group['rows'] as $row)
                                    <tr>
                                        <td class="whitespace-nowrap px-4 py-3 text-gray-900">{{ $row['student']->user->name }} <span class="text-gray-500">(NIS {{ $row['student']->nis }} · NISN {{ $row['student']->nisn }})</span></td>
                                        @foreach ($categories as $category)
                                            @php($metric = $row['categoryPoints'][$category->id] ?? null)
                                            <td class="whitespace-nowrap px-4 py-3 {{ ($metric['points'] ?? 0) < 0 ? 'text-red-700' : 'text-green-700' }}">
                                                @if ($metric && $metric['frequency'] > 0)
                                                    {{ $metric['frequency'] }} kali ({{ $metric['points'] > 0 ? '+' : '' }}{{ $metric['points'] }} poin)
                                                @else
                                                    -
                                                @endif
                                            </td>
                                        @endforeach
                                        <td class="px-4 py-3">{{ $row['attendanceCount'] > 0 ? $row['attendanceCount'].' kali' : '-' }}</td>
                                        <td class="whitespace-nowrap px-4 py-3 text-right font-semibold {{ $row['totalPoints'] < 0 ? 'text-red-700' : 'text-green-700' }}">{{ $row['totalPoints'] > 0 ? '+' : '' }}{{ $row['totalPoints'] }}</td>
                                    </tr>
                                @endforeach
                                <tr class="bg-gray-50 font-semibold">
                                    <td class="px-4 py-3">Rekap {{ $group['name'] }}</td>
                                    @foreach ($categories as $category)
                                        @php($metric = $group['categoryTotals'][$category->id] ?? ['frequency' => 0, 'points' => 0])
                                        <td class="px-4 py-3">{{ $metric['frequency'] > 0 ? $metric['frequency'].' kali ('.($metric['points'] > 0 ? '+' : '').$metric['points'].' poin)' : '-' }}</td>
                                    @endforeach
                                    <td class="px-4 py-3">{{ $group['attendanceCount'] > 0 ? $group['attendanceCount'].' kehadiran' : '-' }}</td>
                                    <td class="px-4 py-3 text-right">{{ $group['totalPoints'] > 0 ? '+' : '' }}{{ $group['totalPoints'] }}</td>
                                </tr>
                            </tbody>
                        @empty
                            <tbody><tr><td colspan="{{ $categories->count() + 3 }}" class="px-4 py-10 text-center text-gray-500">Tidak ada data siswa sesuai filter.</td></tr></tbody>
                        @endforelse
                    </table>
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
