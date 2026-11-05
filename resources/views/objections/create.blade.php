<x-app-layout>
    <x-slot name="header">
        <h1 class="h3 fw-bold mb-0">Pengajuan Keberatan</h1>
    </x-slot>

    <div class="row g-4">
        <div class="col-xl-8">
            <section class="card border-0 shadow-sm">
                <div class="card-body p-3 p-lg-4">
                    <p class="text-secondary">Pilih transaksi milik Anda dan jelaskan alasan agar guru dapat meninjau catatan tersebut.</p>
                    <form method="POST" action="{{ route('objections.store') }}" class="row g-3" enctype="multipart/form-data">
                        @csrf
                        <div class="col-12">
                            <label class="form-label" for="point_transaction_id">Transaksi poin</label>
                            <select class="form-select" id="point_transaction_id" name="point_transaction_id" required>
                                <option value="">Pilih transaksi</option>
                                @foreach ($transactions as $transaction)
                                    <option value="{{ $transaction->id }}" @selected(old('point_transaction_id') == $transaction->id)>
                                        {{ $transaction->transaction_date->format('d/m/Y') }} · {{ $transaction->category->name }}
                                        ({{ $transaction->points > 0 ? '+' : '' }}{{ $transaction->points }})
                                    </option>
                                @endforeach
                            </select>
                            @if ($transactions->isEmpty())
                                <div class="form-text">Belum ada transaksi yang dapat diajukan keberatan.</div>
                            @endif
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="reason">Alasan keberatan</label>
                            <textarea class="form-control" id="reason" name="reason" rows="5" minlength="10" maxlength="2000" required>{{ old('reason') }}</textarea>
                            <div class="form-text">Jelaskan bagian yang tidak sesuai (10–2000 karakter).</div>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="supporting_note">Catatan pendukung (opsional)</label>
                            <textarea class="form-control" id="supporting_note" name="supporting_note" rows="3" maxlength="5000">{{ old('supporting_note') }}</textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="evidence">Bukti pendukung (opsional)</label>
                            <input class="form-control" id="evidence" name="evidence" type="file" accept=".jpg,.jpeg,.png,.pdf">
                            <div class="form-text">JPG, PNG, atau PDF; maksimal 5 MB. File hanya dapat diakses guru yang meninjau.</div>
                        </div>
                        <div class="col-12 d-flex justify-content-end gap-2">
                            <a class="btn btn-outline-secondary" href="{{ route('dashboard') }}">Batal</a>
                            <button class="btn btn-primary" type="submit" @disabled($transactions->isEmpty())>Kirim pengajuan</button>
                        </div>
                    </form>
                </div>
            </section>
        </div>
        <div class="col-xl-4">
            <section class="card border-0 shadow-sm">
                <div class="card-body">
                    <h2 class="h5 fw-bold">Status pengajuan saya</h2>
                    @forelse ($objections as $objection)
                        <article class="border-top py-3">
                            <div class="d-flex justify-content-between gap-2">
                                <span class="fw-semibold">{{ $objection->source?->category?->name ?? 'Transaksi poin' }}</span>
                                <span class="badge {{ match($objection->status) {'approved' => 'text-bg-success', 'rejected' => 'text-bg-danger', default => 'text-bg-warning'} }}">{{ ucfirst($objection->status) }}</span>
                            </div>
                            <p class="small text-secondary mb-1">{{ $objection->reason }}</p>
                            @if ($objection->decision_reason)
                                <p class="small mb-0"><strong>Tinjauan:</strong> {{ $objection->decision_reason }}</p>
                            @endif
                            @if ($objection->correctionTransaction)
                                <p class="small text-success mb-0">Koreksi ledger: {{ $objection->correctionTransaction->points > 0 ? '+' : '' }}{{ $objection->correctionTransaction->points }} poin</p>
                            @endif
                        </article>
                    @empty
                        <p class="text-secondary mb-0">Belum ada pengajuan keberatan.</p>
                    @endforelse
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
