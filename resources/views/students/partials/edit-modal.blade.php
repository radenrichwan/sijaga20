<div class="modal fade" id="editStudent{{ $student->id }}" tabindex="-1" aria-labelledby="editStudentTitle{{ $student->id }}" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <form class="modal-content" method="POST" action="{{ route('students.update', $student) }}">
            @csrf
            @method('PUT')
            <div class="modal-header">
                <h2 class="modal-title fs-5 fw-bold" id="editStudentTitle{{ $student->id }}">Edit Data Siswa</h2>
                <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                @include('students.partials.student-fields', ['student' => $student, 'idPrefix' => 'student-'.$student->id])
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Batal</button>
                <button class="btn btn-primary" type="submit">Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>
