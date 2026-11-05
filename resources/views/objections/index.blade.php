<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="h3 fw-bold mb-1">Pengelolaan Keberatan Siswa</h1>
            <p class="text-secondary mb-0">Persetujuan membuat transaksi koreksi baru; riwayat transaksi dan sumber tetap dipertahankan.</p>
        </div>
    </x-slot>

    <section class="card border-0 shadow-sm">
        <div class="card-header bg-white py-3">
            <form method="GET" action="{{ route('objections.index') }}" class="row g-2 align-items-end">
                <div class="col-sm-5 col-md-3">
                    <label class="form-label" for="status">Status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="">Semua status</option>
                        @foreach (['pending' => 'Menunggu', 'approved' => 'Disetujui', 'rejected' => 'Ditolak'] as $value => $label)
                            <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-auto"><button class="btn btn-outline-primary" type="submit">Filter</button></div>
            </form>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Siswa</th><th>Transaksi</th><th>Alasan Siswa</th><th>Bukti/Catatan</th><th>Status</th><th>Keputusan Guru</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($objections as $objection)
                        <tr>
                            <td class="text-nowrap">
                                <span class="fw-semibold">{{ $objection->student->user->name }}</span>
                                <span class="d-block small text-secondary">NIS {{ $objection->student->nis }}</span>
                            </td>
                            <td class="text-nowrap">
                                {{ $objection->source?->transaction_date?->format('d/m/Y') ?? '—' }} ·
                                {{ $objection->source?->category?->name ?? 'Transaksi poin' }}
                                <span class="badge {{ ($objection->source?->points ?? 0) > 0 ? 'text-bg-success' : 'text-bg-danger' }}">
                                    {{ ($objection->source?->points ?? 0) > 0 ? '+' : '' }}{{ $objection->source?->points ?? 0 }}
                                </span>
                                <span class="d-block small text-secondary">{{ $objection->source?->description }}</span>
                            </td>
                            <td class="text-break" style="min-width: 220px">{{ $objection->reason }}</td>
                            <td style="min-width: 180px">
                                @if ($objection->supporting_note)
                                    <p class="small mb-1">{{ $objection->supporting_note }}</p>
                                @endif
                                @if ($objection->evidence_path)
                                    <a class="btn btn-sm btn-outline-secondary" href="{{ route('objections.evidence', $objection) }}">Unduh bukti</a>
                                @else
                                    <span class="small text-secondary">Tidak ada file</span>
                                @endif
                            </td>
                            <td>
                                <span class="badge {{ match($objection->status) {'approved' => 'text-bg-success', 'rejected' => 'text-bg-danger', default => 'text-bg-warning'} }}">
                                    {{ ucfirst($objection->status) }}
                                </span>
                                @if ($objection->correctionTransaction)
                                    <span class="d-block small text-success mt-1">
                                        Koreksi {{ $objection->correctionTransaction->points > 0 ? '+' : '' }}{{ $objection->correctionTransaction->points }}
                                    </span>
                                @endif
                            </td>
                            <td style="min-width: 240px">
                                @if ($objection->status === 'pending')
                                    <form method="POST" action="{{ route('objections.decide', $objection) }}" class="d-grid gap-2">
                                        @csrf
                                        @method('PATCH')
                                        <select class="form-select form-select-sm" name="status" aria-label="Keputusan" required>
                                            <option value="approved">Setujui dan koreksi ledger</option>
                                            <option value="rejected">Tolak</option>
                                        </select>
                                        <textarea class="form-control form-control-sm" name="decision_reason" rows="2" minlength="5" maxlength="2000" placeholder="Alasan peninjauan (wajib)" required></textarea>
                                        <button class="btn btn-sm btn-primary" type="submit">Simpan keputusan</button>
                                    </form>
                                @else
                                    <span class="small">{{ $objection->decision_reason }}</span>
                                    <span class="d-block small text-secondary">
                                        {{ $objection->decidedBy?->name }} · {{ $objection->decided_at?->format('d/m/Y H:i') }}
                                    </span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-secondary py-4">Belum ada pengajuan keberatan.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-body">{{ $objections->links() }}</div>
    </section>
</x-app-layout>
