<div class="col-md-6">
    <label class="form-label" for="{{ $idPrefix }}-date">Tanggal keterlambatan</label>
    <input class="form-control" id="{{ $idPrefix }}-date" name="attendance_date" type="date"
        max="{{ today()->toDateString() }}" value="{{ old('attendance_date', $record?->attendance_date?->toDateString() ?? today()->toDateString()) }}" required>
</div>
<div class="col-md-6">
    <label class="form-label" for="{{ $idPrefix }}-time">Waktu tiba (WIB)</label>
    <input class="form-control" id="{{ $idPrefix }}-time" name="arrival_time" type="time" min="06:31"
        value="{{ old('arrival_time', $record?->arrival_time ? substr((string) $record->arrival_time, 0, 5) : '06:31') }}" required>
    <div class="form-text">Pukul 06.30 WIB tepat waktu; mulai 06.31 WIB tercatat terlambat.</div>
</div>
<div class="col-12">
    <label class="form-label" for="{{ $idPrefix }}-description">Catatan</label>
    <textarea class="form-control" id="{{ $idPrefix }}-description" name="description" rows="3" maxlength="2000">{{ old('description', $record?->description) }}</textarea>
</div>
