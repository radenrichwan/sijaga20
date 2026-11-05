<x-app-layout>
    <x-slot name="header">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <h1 class="h3 fw-bold mb-1">Preview Pembagian Jadwal</h1>
                <p class="text-secondary mb-0">
                    {{ $startDate->translatedFormat('d M Y') }} – {{ $endDate->translatedFormat('d M Y') }}.
                    Pilih 4–6 petugas per hari; selisih beban setiap minggu maksimal satu hari.
                </p>
            </div>
            <a class="btn btn-outline-secondary" href="{{ route('duty-schedules.index', ['period' => $period, 'start_date' => $startDate->toDateString()]) }}">Kembali</a>
        </div>
    </x-slot>

    <form method="POST" action="{{ route('duty-schedules.confirm') }}">
        @csrf
        <input type="hidden" name="period" value="{{ $period }}">
        <input type="hidden" name="start_date" value="{{ $startDate->toDateString() }}">
        <div class="row g-3">
            @foreach ($schedule as $date => $selected)
                <div class="col-md-6 col-xl-4">
                    <section class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-white fw-semibold">{{ \Illuminate\Support\Carbon::parse($date)->translatedFormat('l, d M Y') }}</div>
                        <div class="card-body">
                            <label for="schedule-{{ $date }}" class="form-label">Petugas (4–6)</label>
                            <select class="form-select" id="schedule-{{ $date }}" name="schedule[{{ $date }}][]" multiple size="6" required>
                                @foreach ($operationalUsers as $member)
                                    <option value="{{ $member->id }}" @selected(in_array($member->id, $selected, true))>
                                        {{ $member->name }} ({{ $member->username }})
                                    </option>
                                @endforeach
                            </select>
                            <div class="form-text">Gunakan Ctrl (Windows) atau Command (Mac) untuk memilih beberapa petugas.</div>
                        </div>
                    </section>
                </div>
            @endforeach
        </div>
        <div class="d-flex flex-wrap justify-content-end gap-2 mt-4">
            <a class="btn btn-outline-secondary" href="{{ route('duty-schedules.index', ['period' => $period, 'start_date' => $startDate->toDateString()]) }}">Batalkan</a>
            <button class="btn btn-primary" type="submit">Konfirmasi dan simpan jadwal</button>
        </div>
    </form>
</x-app-layout>
