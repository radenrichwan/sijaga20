<label class="form-label" for="student_nis">Cari siswa (NIS, nama, atau NISN)</label>
<select class="form-select select2-student" id="student_nis" name="student_nis" required>
    <option value="">Pilih siswa</option>
    @foreach ($students as $student)
        <option value="{{ $student->nis }}" @selected(old('student_nis') === $student->nis) @disabled(!$student->is_active)>
            {{ $student->user->name }} — NIS {{ $student->nis }} — NISN {{ $student->nisn }} — {{ $student->class_name }}{{ $student->is_active ? '' : ' — Nonaktif' }}
        </option>
    @endforeach
</select>
