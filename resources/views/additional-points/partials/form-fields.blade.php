<div class="col-12">
    <label class="form-label" for="{{ $idPrefix }}-student">Siswa</label>
    <select class="form-select select2-student" id="{{ $idPrefix }}-student" name="student_nis" required>
        <option value="">Pilih siswa</option>
        @foreach ($students as $student)
            <option value="{{ $student->nis }}" @selected(old('student_nis', $point?->student?->nis) === $student->nis)
                @disabled(!$student->is_active && old('student_nis', $point?->student?->nis) !== $student->nis)>
                {{ $student->user->name }} — NIS {{ $student->nis }} — {{ $student->class_name }}{{ $student->is_active ? '' : ' — Nonaktif' }}
            </option>
        @endforeach
    </select>
</div>
<div class="col-md-4">
    <label class="form-label" for="{{ $idPrefix }}-points">Jumlah poin</label>
    <input class="form-control" id="{{ $idPrefix }}-points" name="points" type="number" min="1" max="10000" step="1"
        value="{{ old('points', $point?->points) }}" required>
</div>
<div class="col-md-8">
    <label class="form-label" for="{{ $idPrefix }}-date">Tanggal</label>
    <input class="form-control" id="{{ $idPrefix }}-date" name="awarded_date" type="date" max="{{ today()->toDateString() }}"
        value="{{ old('awarded_date', $point?->awarded_date?->toDateString() ?? today()->toDateString()) }}" required>
</div>
<div class="col-12">
    <label class="form-label" for="{{ $idPrefix }}-reason">Alasan / keterangan</label>
    <textarea class="form-control" id="{{ $idPrefix }}-reason" name="reason" rows="3" maxlength="255" required>{{ old('reason', $point?->reason) }}</textarea>
</div>
