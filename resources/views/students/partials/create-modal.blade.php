<div class="modal fade" id="createStudentModal" tabindex="-1" aria-labelledby="createStudentTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <form class="modal-content" method="POST" action="{{ route('students.store') }}">
            @csrf
            <div class="modal-header">
                <h2 class="modal-title fs-5 fw-bold" id="createStudentTitle">Tambah Siswa</h2>
                <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                @include('students.partials.student-fields', ['idPrefix' => 'new-student'])
                <p class="alert alert-info small mt-3 mb-0">Akun login dibuat otomatis dengan username NIS dan kata sandi awal yang sama dengan NIS.</p>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Batal</button>
                <button class="btn btn-primary" type="submit">Simpan Siswa</button>
            </div>
        </form>
    </div>
</div>
