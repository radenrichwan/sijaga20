<form method="POST" action="{{ route('morning-rewards.store') }}" id="morning-award-form" class="row g-3">
    @csrf
    <input type="hidden" name="reward_date" value="{{ today()->toDateString() }}">
    <div class="col-12">
        <label class="form-label" for="student_id">Siswa</label>
        <select class="form-select select2-student" name="student_id" id="student_id" required>
            <option value="">Pilih siswa</option>
            @foreach ($students as $studentOption)
                <option value="{{ $studentOption->id }}" @selected(old('student_id') == $studentOption->id)
                    @disabled(!$studentOption->is_active)>
                    {{ $studentOption->user->name }} — {{ $studentOption->nis }} ({{ $studentOption->class_name }}){{ $studentOption->is_active ? '' : ' — Nonaktif' }}
                </option>
            @endforeach
        </select>
    </div>
    <div class="col-sm-6">
        <label class="form-label" for="arrival_time">Waktu tiba</label>
        <input class="form-control" type="time" min="04:00" max="05:30" name="arrival_time" id="arrival_time" value="{{ old('arrival_time', '05:00') }}" required>
    </div>
    <div class="col-sm-6">
        <label class="form-label" for="rank">Peringkat</label>
        <select class="form-select" name="rank" id="rank" required>
            <option value="">Pilih peringkat</option>
            @foreach (range(1, 10) as $rankOption)
                @if (!in_array($rankOption, $usedRewardRanks, true))
                    <option value="{{ $rankOption }}" @selected(old('rank') == $rankOption)>{{ $rankOption }}</option>
                @endif
            @endforeach
        </select>
    </div>
    <div class="col-12 d-flex align-items-center justify-content-between gap-2">
        <span class="small text-secondary">Syarat waktu: 04.00–05.30 · Poin: +15</span>
        <button class="btn btn-success" type="submit" @disabled(count($usedRewardRanks) >= 10)>Simpan penghargaan</button>
    </div>
</form>

<div class="mt-3">
    <a class="btn btn-sm btn-outline-success" href="{{ route('morning-rewards.index') }}">Kelola seluruh penghargaan pagi</a>
</div>
