<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Transkrip Poin - {{ $student->user->name }}</title>
    <style>
        body { color: #172b4d; font-family: DejaVu Sans, sans-serif; font-size: 10px; }
        h1 { color: #102a43; font-size: 20px; margin: 0 0 4px; }
        .subtitle { color: #526579; margin-bottom: 20px; }
        .summary { border: 1px solid #dce5ef; border-radius: 6px; margin-bottom: 18px; padding: 10px; }
        .summary p { margin: 3px 0; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #dce5ef; padding: 7px; text-align: left; }
        th { background: #edf3fa; }
        .right { text-align: right; }
        .positive { color: #198754; }
        .negative { color: #dc3545; }
    </style>
</head>
<body>
    <h1>Transkrip Riwayat Poin Siswa</h1>
    <p class="subtitle">SIJAGA 20 · SMKN 20 Jakarta</p>

    <div class="summary">
        <p><strong>Nama:</strong> {{ $student->user->name }}</p>
        <p><strong>NIS:</strong> {{ $student->nis }} &nbsp; <strong>NISN:</strong> {{ $student->nisn }}</p>
        <p><strong>Kelas:</strong> {{ $student->class_name }} &nbsp; <strong>Jurusan:</strong> {{ $student->major }}</p>
        <p><strong>Saldo poin saat ini:</strong> {{ number_format($student->current_points) }} poin</p>
        <p><strong>Dicetak:</strong> {{ now('Asia/Jakarta')->translatedFormat('d F Y H:i') }} WIB</p>
    </div>

    <table>
        <thead>
            <tr><th>Tanggal</th><th>Kategori</th><th>Catatan</th><th>Pencatat</th><th class="right">Poin</th></tr>
        </thead>
        <tbody>
            @forelse ($transactions as $transaction)
                <tr>
                    <td>{{ $transaction->transaction_date->format('d/m/Y') }}</td>
                    <td>{{ $transaction->category->name }}</td>
                    <td>{{ $transaction->description ?: '—' }}</td>
                    <td>{{ $transaction->recordedBy->name }}</td>
                    <td class="right {{ $transaction->points >= 0 ? 'positive' : 'negative' }}">
                        {{ $transaction->points > 0 ? '+' : '' }}{{ number_format($transaction->points) }}
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" style="text-align: center;">Belum ada transaksi poin.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
