<div class="modal fade" id="studentDetail{{ $student->id }}" tabindex="-1" aria-labelledby="studentDetailTitle{{ $student->id }}" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title fs-5 fw-bold" id="studentDetailTitle{{ $student->id }}">Detail Siswa</h2>
                <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <dl class="row mb-0">
                    <dt class="col-sm-4">Nama lengkap</dt><dd class="col-sm-8">{{ $student->user->name }}</dd>
                    <dt class="col-sm-4">NIS / username</dt><dd class="col-sm-8">{{ $student->nis }}</dd>
                    <dt class="col-sm-4">NISN</dt><dd class="col-sm-8">{{ $student->nisn }}</dd>
                    <dt class="col-sm-4">Jenis kelamin</dt><dd class="col-sm-8">{{ $student->gender }}</dd>
                    <dt class="col-sm-4">Tingkat / jurusan</dt><dd class="col-sm-8">{{ $student->grade }} · {{ $student->major }}</dd>
                    <dt class="col-sm-4">Rombongan belajar</dt><dd class="col-sm-8">{{ $student->class_name }}</dd>
                    @php
                        $pointBadge = $student->current_points >= 100 ? 'success' : ($student->current_points >= 75 ? 'warning' : 'danger');
                    @endphp
                    <dt class="col-sm-4">Poin saat ini</dt><dd class="col-sm-8"><span class="badge text-bg-{{ $pointBadge }}">{{ number_format($student->current_points) }} poin</span></dd>
                    <dt class="col-sm-4">Status</dt><dd class="col-sm-8">{{ $student->is_active ? 'Aktif' : 'Nonaktif' }}</dd>
                </dl>
            </div>
            <div class="modal-footer">
                <a class="btn btn-outline-primary" href="{{ route('students.show', $student) }}">Riwayat poin</a>
                <button class="btn btn-secondary" type="button" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>
