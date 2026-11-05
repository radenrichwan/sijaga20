<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Laporan Poin Siswa - SMKN 20 Jakarta</title>
    <style>
        @page { margin: 22mm 12mm 18mm; }
        body { color: #212529; font: 10px DejaVu Sans, sans-serif; }
        h1, h2, p { margin: 0; }
        .school-header { border-bottom: 3px double #212529; margin-bottom: 14px; padding: 4px 0 10px; text-align: center; }
        .school-header h1 { font-size: 17px; margin-bottom: 4px; }
        .school-header h2 { font-size: 12px; margin-bottom: 3px; }
        .filters { line-height: 1.7; margin: 10px 0; }
        .summary { margin: 8px 0 14px; }
        .table { border-collapse: collapse; margin-bottom: 12px; width: 100%; }
        .table th, .table td { border: 1px solid #adb5bd; padding: 5px; vertical-align: top; }
        .table th { background: #e9ecef; font-weight: bold; }
        .class-heading { background: #dee2e6; font-weight: bold; }
        .text-end { text-align: right; }
        .muted { color: #6c757d; }
        .signatures { margin-top: 40px; width: 100%; }
        .signatures td { border: 0; text-align: center; width: 50%; }
        .signature-space { height: 62px; }
        .page-break { page-break-after: always; }
        tr { page-break-inside: avoid; }
    </style>
</head>
<body>
    <header class="school-header">
        <h1>SMK NEGERI 20 JAKARTA</h1>
        <h2>LAPORAN FREKUENSI DAN POIN SISWA</h2>
        <p>Rekapitulasi kategori poin dan kehadiran piket</p>
    </header>

    <p class="filters">
        Kelas: {{ $filters['class_name'] ?? 'Semua kelas' }} ·
        Jurusan: {{ $filters['major'] ?? 'Semua jurusan' }} ·
        Bulan/Tahun: {{ isset($filters['month']) ? \Carbon\Carbon::create()->month((int) $filters['month'])->translatedFormat('F') : 'Semua bulan' }}/{{ $filters['year'] ?? 'Semua tahun' }} ·
        Semester: {{ ($filters['semester'] ?? '') === '1' ? '1 (Juli–Desember)' : ((($filters['semester'] ?? '') === '2') ? '2 (Januari–Juni)' : 'Semua semester') }} ·
        Rentang: {{ $filters['start_date'] ?? '—' }} s.d. {{ $filters['end_date'] ?? '—' }} ·
        Kategori: {{ $selectedCategory?->name ?? 'Semua kategori' }}
    </p>
    <p class="summary"><strong>{{ number_format($totalStudents) }} siswa</strong> · Total akumulasi transaksi: <strong>{{ $totalPoints > 0 ? '+' : '' }}{{ number_format($totalPoints) }} poin</strong></p>

    @foreach ($classes as $group)
        <table class="table">
            <thead>
                <tr>
                    <th colspan="{{ $categories->count() + 4 }}" class="class-heading">Kelas {{ $group['name'] }}</th>
                </tr>
                <tr>
                    <th>Siswa (NIS/NISN)</th>
                    @foreach ($categories as $category)
                        <th>{{ $category->name }}<br><span class="muted">frekuensi (akumulasi poin)</span></th>
                    @endforeach
                    <th>Absensi piket</th>
                    <th class="text-end">Total poin</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($group['rows'] as $row)
                    <tr>
                        <td>{{ $row['student']->user->name }}<br><span class="muted">{{ $row['student']->nis }} / {{ $row['student']->nisn }}</span></td>
                        @foreach ($categories as $category)
                            @php($metric = $row['categoryPoints'][$category->id] ?? null)
                            <td>
                                @if ($metric && $metric['frequency'] > 0)
                                    {{ $metric['frequency'] }} kali ({{ $metric['points'] > 0 ? '+' : '' }}{{ $metric['points'] }} poin)
                                @else
                                    -
                                @endif
                            </td>
                        @endforeach
                        <td>{{ $row['attendanceCount'] > 0 ? $row['attendanceCount'].' kali' : '-' }}</td>
                        <td class="text-end">{{ $row['totalPoints'] > 0 ? '+' : '' }}{{ $row['totalPoints'] }}</td>
                    </tr>
                @endforeach
                <tr class="class-heading">
                    <td>Rekap kelas</td>
                    @foreach ($categories as $category)
                        @php($metric = $group['categoryTotals'][$category->id] ?? ['frequency' => 0, 'points' => 0])
                        <td>{{ $metric['frequency'] > 0 ? $metric['frequency'].' kali ('.($metric['points'] > 0 ? '+' : '').$metric['points'].' poin)' : '-' }}</td>
                    @endforeach
                    <td>{{ $group['attendanceCount'] > 0 ? $group['attendanceCount'].' kehadiran' : '-' }}</td>
                    <td class="text-end">{{ $group['totalPoints'] > 0 ? '+' : '' }}{{ $group['totalPoints'] }}</td>
                </tr>
            </tbody>
        </table>
    @endforeach

    @if ($classes->isEmpty())
        <p>Tidak ada data siswa sesuai filter.</p>
    @endif

    <table class="signatures">
        <tr>
            <td>Mengetahui,<br>Wali Kelas<div class="signature-space"></div>(........................................)</td>
            <td>Jakarta, {{ now()->translatedFormat('d F Y') }}<br>Guru Piket<div class="signature-space"></div>(........................................)</td>
        </tr>
    </table>
</body>
</html>
