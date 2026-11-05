@php($studentValue = $student ?? null)
<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label" for="{{ $idPrefix }}-nis">NIS (username)</label>
        <input class="form-control" id="{{ $idPrefix }}-nis" name="nis" value="{{ old('nis', $studentValue?->nis) }}"
            inputmode="numeric" pattern="[0-9]{5,20}" minlength="5" maxlength="20" required>
        <div class="form-text">5–20 digit, termasuk angka nol di depan.</div>
    </div>
    <div class="col-md-6">
        <label class="form-label" for="{{ $idPrefix }}-nisn">NISN</label>
        <input class="form-control" id="{{ $idPrefix }}-nisn" name="nisn" value="{{ old('nisn', $studentValue?->nisn) }}"
            inputmode="numeric" maxlength="20" required>
    </div>
    <div class="col-md-6">
        <label class="form-label" for="{{ $idPrefix }}-name">Nama lengkap</label>
        <input class="form-control" id="{{ $idPrefix }}-name" name="name" value="{{ old('name', $studentValue?->user?->name) }}" maxlength="255" required>
    </div>
    <div class="col-md-6">
        <label class="form-label" for="{{ $idPrefix }}-gender">Jenis kelamin</label>
        <select class="form-select" id="{{ $idPrefix }}-gender" name="gender" required>
            <option value="">Pilih jenis kelamin</option>
            @foreach (['Laki-laki', 'Perempuan'] as $gender)
                <option value="{{ $gender }}" @selected(old('gender', $studentValue?->gender) === $gender)>{{ $gender }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-4">
        <label class="form-label" for="{{ $idPrefix }}-grade">Tingkat</label>
        <select class="form-select" id="{{ $idPrefix }}-grade" name="grade" required>
            <option value="">Pilih tingkat</option>
            @foreach (['X', 'XI', 'XII'] as $grade)
                <option value="{{ $grade }}" @selected(old('grade', $studentValue?->grade) === $grade)>{{ $grade }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-8">
        <label class="form-label" for="{{ $idPrefix }}-major">Jurusan</label>
        <input class="form-control" id="{{ $idPrefix }}-major" name="major" value="{{ old('major', $studentValue?->major) }}" maxlength="100" required>
    </div>
    <div class="col-md-8">
        <label class="form-label" for="{{ $idPrefix }}-class">Rombongan belajar</label>
        <input class="form-control" id="{{ $idPrefix }}-class" name="class_name" value="{{ old('class_name', $studentValue?->class_name) }}" maxlength="50" placeholder="XI RPL 1" required>
    </div>
    @if ($studentValue)
        <div class="col-md-4">
            <label class="form-label" for="{{ $idPrefix }}-status">Status akun</label>
            <select class="form-select" id="{{ $idPrefix }}-status" name="is_active" required>
                <option value="1" @selected(old('is_active', (int) $studentValue->is_active) == 1)>Aktif</option>
                <option value="0" @selected(old('is_active', (int) $studentValue->is_active) == 0)>Nonaktif</option>
            </select>
        </div>
    @else
        <input type="hidden" name="is_active" value="1">
    @endif
</div>
