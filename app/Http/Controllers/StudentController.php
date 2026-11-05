<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ImportStudentsRequest;
use App\Http\Requests\StoreStudentRequest;
use App\Http\Requests\UpdateStudentRequest;
use App\Models\Role;
use App\Models\Student;
use App\Models\User;
use App\Services\ActivityLogService;
use App\Services\StudentSpreadsheetReader;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StudentController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Student::class);
        $filters = $this->validatedFilters($request);
        $students = $this->studentQuery($filters)
            ->withCalculatedPoints()
            ->with('user')
            ->orderBy('grade')
            ->orderBy('class_name')
            ->orderBy('nis')
            ->paginate(15)
            ->withQueryString();

        return view('students.index', [
            'students' => $students,
            'filters' => $filters,
            'majors' => Student::query()->whereNotNull('major')->distinct()->orderBy('major')->pluck('major'),
        ]);
    }

    public function create(): RedirectResponse
    {
        $this->authorize('create', Student::class);

        return to_route('students.index');
    }

    public function store(StoreStudentRequest $request, ActivityLogService $activityLogs): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($data, $request, $activityLogs): void {
            $user = User::query()->create([
                'name' => $data['name'],
                'username' => $data['nis'],
                'password' => $data['nis'],
                'role_id' => Role::query()->where('name', 'siswa')->firstOrFail()->id,
                'is_active' => $data['is_active'] ?? true,
            ]);

            $student = $user->student()->create($this->studentAttributes($data));
            $activityLogs->record($request->user(), 'create', 'student', $student, null, [
                ...$student->only(['nis', 'nisn', 'gender', 'grade', 'major', 'class_name', 'is_active']),
                'name' => $user->name,
            ]);
        });

        return to_route('students.index')->with('status', 'Data siswa dan akun login berhasil ditambahkan.');
    }

    public function show(Student $student): View
    {
        $this->authorize('view', $student);
        $student->load('user');
        $ledgers = $student->pointTransactions()
            ->with(['student.user', 'category', 'recordedBy', 'counterEntryFor.category'])
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->paginate(20);

        return view('students.show', compact('student', 'ledgers'));
    }

    public function transcript(Student $student): Response
    {
        $this->authorize('view', $student);
        $student->loadMissing('user');
        $transactions = $student->pointTransactions()
            ->with(['student.user', 'category', 'recordedBy', 'counterEntryFor.category'])
            ->where('is_active', true)
            ->orderBy('transaction_date')
            ->orderBy('id')
            ->get();

        return Pdf::loadView('students.transcript', compact('student', 'transactions'))
            ->setPaper('a4')
            ->download('transkrip-poin-'.$student->nis.'.pdf');
    }

    public function edit(Student $student): RedirectResponse
    {
        $this->authorize('update', $student);

        return to_route('students.index');
    }

    public function update(UpdateStudentRequest $request, Student $student, ActivityLogService $activityLogs): RedirectResponse
    {
        $data = $request->validated();
        $oldValues = [
            ...$student->only(['nis', 'nisn', 'gender', 'grade', 'major', 'class_name', 'is_active']),
            'name' => $student->user->name,
        ];

        DB::transaction(function () use ($data, $student, $request, $activityLogs, $oldValues): void {
            $student->user()->update([
                'name' => $data['name'],
                'username' => $data['nis'],
                'is_active' => $data['is_active'],
            ]);
            $student->update($this->studentAttributes($data));
            $activityLogs->record($request->user(), 'update', 'student', $student, $oldValues, [
                ...$student->fresh()->only(['nis', 'nisn', 'gender', 'grade', 'major', 'class_name', 'is_active']),
                'name' => $data['name'],
            ]);
        });

        return to_route('students.index', request()->query())->with('status', 'Data siswa berhasil diperbarui.');
    }

    public function destroy(Student $student): RedirectResponse
    {
        $this->authorize('delete', $student);

        $this->setActiveStatus($student, false, request()->user(), app(ActivityLogService::class));

        return to_route('students.index')->with('status', 'Akun siswa dinonaktifkan. Riwayat dan data siswa tetap tersimpan.');
    }

    public function setStatus(Request $request, Student $student): RedirectResponse
    {
        $this->authorize('update', $student);
        $validated = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        $this->setActiveStatus($student, (bool) $validated['is_active'], $request->user(), app(ActivityLogService::class));

        return to_route('students.index', $request->query())->with(
            'status',
            $validated['is_active'] ? 'Akun siswa berhasil diaktifkan.' : 'Akun siswa berhasil dinonaktifkan.',
        );
    }

    public function export(Request $request): StreamedResponse
    {
        $this->authorize('viewAny', Student::class);
        $students = $this->studentQuery($this->validatedFilters($request))
            ->withCalculatedPoints()
            ->with('user')
            ->orderBy('nis')
            ->get();

        return response()->streamDownload(function () use ($students): void {
            $output = fopen('php://output', 'wb');
            if ($output === false) {
                throw new \RuntimeException('Tidak dapat membuka stream ekspor siswa.');
            }

            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, ['nis', 'nisn', 'name', 'gender', 'grade', 'major', 'class_name', 'is_active']);

            foreach ($students as $student) {
                fputcsv($output, [
                    $student->nis,
                    $student->nisn,
                    $student->user->name,
                    $student->gender,
                    $student->grade,
                    $student->major,
                    $student->class_name,
                    $student->is_active ? '1' : '0',
                ]);
            }

            fclose($output);
        }, 'data-siswa.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function exportExcel(Request $request): StreamedResponse
    {
        $this->authorize('viewAny', Student::class);
        $students = $this->studentQuery($this->validatedFilters($request))
            ->withCalculatedPoints()
            ->with('user')
            ->orderBy('nis')
            ->get();

        return response()->streamDownload(function () use ($students): void {
            $path = tempnam(sys_get_temp_dir(), 'sijaga-students-');
            if ($path === false) {
                throw new \RuntimeException('Tidak dapat menyiapkan file Excel siswa.');
            }

            try {
                $archive = new \ZipArchive;
                if ($archive->open($path, \ZipArchive::OVERWRITE) !== true) {
                    throw new \RuntimeException('Tidak dapat membuat file Excel siswa.');
                }
                $archive->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                    .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
                    .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
                    .'<Default Extension="xml" ContentType="application/xml"/>'
                    .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
                    .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
                    .'</Types>');
                $archive->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                    .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                    .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
                    .'</Relationships>');
                $archive->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                    .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
                    .'<sheets><sheet name="Data Siswa" sheetId="1" r:id="rId1"/></sheets></workbook>');
                $archive->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                    .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                    .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
                    .'</Relationships>');

                $table = [
                    ['nis', 'nisn', 'name', 'gender', 'grade', 'major', 'class_name', 'is_active'],
                ];
                foreach ($students as $student) {
                    $table[] = [
                        $student->nis,
                        $student->nisn,
                        $student->user->name,
                        $student->gender,
                        $student->grade,
                        $student->major,
                        $student->class_name,
                        $student->is_active ? '1' : '0',
                    ];
                }

                $sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                    .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';
                foreach ($table as $rowIndex => $values) {
                    $rowNumber = $rowIndex + 1;
                    $sheet .= '<row r="'.$rowNumber.'">';
                    foreach ($values as $columnIndex => $value) {
                        $column = '';
                        for ($number = $columnIndex + 1; $number > 0; $number = intdiv($number - 1, 26)) {
                            $column = chr(65 + (($number - 1) % 26)).$column;
                        }
                        $cell = htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
                        $sheet .= '<c r="'.$column.$rowNumber.'" t="inlineStr"><is><t xml:space="preserve">'.$cell.'</t></is></c>';
                    }
                    $sheet .= '</row>';
                }
                $sheet .= '</sheetData></worksheet>';
                $archive->addFromString('xl/worksheets/sheet1.xml', $sheet);
                $archive->close();
                readfile($path);
            } finally {
                if (is_file($path)) {
                    unlink($path);
                }
            }
        }, 'data-siswa.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function import(ImportStudentsRequest $request, StudentSpreadsheetReader $reader): RedirectResponse
    {
        $this->authorize('create', Student::class);
        $rows = $reader->read($request->file('file'));
        if ($rows === []) {
            throw ValidationException::withMessages(['file' => 'File tidak berisi baris data.']);
        }

        $header = $this->normalizeHeaders(array_shift($rows)['values']);
        $requiredHeaders = ['nis', 'nisn', 'name', 'gender', 'grade', 'major', 'class_name'];
        $missingHeaders = array_diff($requiredHeaders, $header);
        if ($missingHeaders !== []) {
            throw ValidationException::withMessages([
                'file' => 'Kolom wajib tidak ditemukan: '.implode(', ', $missingHeaders).'.',
            ]);
        }

        $roleId = Role::query()->where('name', 'siswa')->firstOrFail()->id;
        $seenNis = [];
        $seenNisn = [];
        $errors = [];
        $imported = 0;

        foreach ($rows as $row) {
            $values = array_map(static fn (mixed $value): string => trim((string) $value), $row['values']);
            if (count(array_filter($values, static fn (string $value): bool => $value !== '')) === 0) {
                continue;
            }

            $data = [];
            foreach ($header as $index => $key) {
                if ($key !== null) {
                    $data[$key] = $values[$index] ?? '';
                }
            }
            $data['is_active'] = $this->importStatus($data['is_active'] ?? '1');
            $validator = Validator::make($data, [
                'nis' => [
                    'required',
                    'string',
                    'regex:/^[0-9]{5,20}$/',
                    Rule::unique('students', 'nis'),
                    Rule::unique('users', 'username'),
                    Rule::unique('users', 'username'),
                ],
                'nisn' => ['required', 'string', 'regex:/^[0-9]+$/', 'max:20', Rule::unique('students', 'nisn')],
                'name' => ['required', 'string', 'max:255'],
                'gender' => ['required', Rule::in(['Laki-laki', 'Perempuan'])],
                'grade' => ['required', Rule::in(['X', 'XI', 'XII'])],
                'major' => ['required', 'string', 'max:100'],
                'class_name' => ['required', 'string', 'max:50'],
                'is_active' => ['required', 'boolean'],
            ]);

            $rowErrors = $validator->errors()->all();
            if (isset($seenNis[$data['nis'] ?? ''])) {
                $rowErrors[] = 'NIS duplikat dengan baris '.$seenNis[$data['nis']].'.';
            }
            if (isset($seenNisn[$data['nisn'] ?? ''])) {
                $rowErrors[] = 'NISN duplikat dengan baris '.$seenNisn[$data['nisn']].'.';
            }
            if ($rowErrors !== []) {
                $errors[] = ['row' => $row['row'], 'messages' => array_values(array_unique($rowErrors))];

                continue;
            }

            $seenNis[$data['nis']] = $row['row'];
            $seenNisn[$data['nisn']] = $row['row'];
            DB::transaction(function () use ($data, $roleId): void {
                $user = User::query()->create([
                    'name' => $data['name'],
                    'username' => $data['nis'],
                    'password' => $data['nis'],
                    'role_id' => $roleId,
                    'is_active' => $data['is_active'],
                ]);
                $user->student()->create($this->studentAttributes($data));
            });
            $imported++;
        }

        return to_route('students.index')->with('status', "{$imported} siswa berhasil diimpor.")
            ->with('import_errors', $errors);
    }

    public function downloadImportTemplate(): StreamedResponse
    {
        $this->authorize('create', Student::class);

        return response()->streamDownload(static function (): void {
            $output = fopen('php://output', 'wb');
            if ($output === false) {
                throw new \RuntimeException('Template impor siswa tidak dapat dibuat.');
            }

            fputcsv($output, ['nis', 'nisn', 'name', 'gender', 'grade', 'major', 'class_name', 'is_active'], ',', '"', '\\', "\r\n");
            fclose($output);
        }, 'template-import-siswa.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function validatedFilters(Request $request): array
    {
        return $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'grade' => ['nullable', Rule::in(['X', 'XI', 'XII'])],
            'major' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ]);
    }

    private function studentQuery(array $filters): Builder
    {
        return Student::query()
            ->when(($filters['search'] ?? '') !== '', function (Builder $query) use ($filters): void {
                $search = trim($filters['search']);
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('nis', 'like', "%{$search}%")
                        ->orWhere('nisn', 'like', "%{$search}%")
                        ->orWhereHas('user', fn (Builder $users) => $users->where('name', 'like', "%{$search}%"));
                });
            })
            ->when(isset($filters['grade']) && $filters['grade'] !== '', fn (Builder $query) => $query->where('grade', $filters['grade']))
            ->when(isset($filters['major']) && $filters['major'] !== '', fn (Builder $query) => $query->where('major', $filters['major']))
            ->when(isset($filters['status']) && $filters['status'] !== '', fn (Builder $query) => $query->where('is_active', $filters['status'] === 'active'));
    }

    private function normalizeHeaders(array $values): array
    {
        $aliases = [
            'nis' => 'nis',
            'nisn' => 'nisn',
            'name' => 'name',
            'nama' => 'name',
            'nama_lengkap' => 'name',
            'gender' => 'gender',
            'jenis_kelamin' => 'gender',
            'grade' => 'grade',
            'tingkat' => 'grade',
            'major' => 'major',
            'jurusan' => 'major',
            'class_name' => 'class_name',
            'rombel' => 'class_name',
            'is_active' => 'is_active',
            'status' => 'is_active',
        ];

        return array_map(static function (mixed $value) use ($aliases): ?string {
            $header = strtolower(trim((string) $value));
            $header = preg_replace('/^\xEF\xBB\xBF/', '', $header) ?? $header;

            return $aliases[$header] ?? null;
        }, $values);
    }

    private function importStatus(string $value): bool|string
    {
        return match (strtolower(trim($value))) {
            '', '1', 'true', 'aktif', 'active', 'ya' => true,
            '0', 'false', 'nonaktif', 'inactive', 'tidak' => false,
            default => $value,
        };
    }

    private function studentAttributes(array $data): array
    {
        return [
            'nis' => $data['nis'],
            'nisn' => $data['nisn'],
            'gender' => $data['gender'],
            'grade' => $data['grade'],
            'major' => $data['major'],
            'class_name' => $data['class_name'],
            'is_active' => $data['is_active'] ?? true,
        ];
    }

    private function setActiveStatus(Student $student, bool $isActive, User $actor, ActivityLogService $activityLogs): void
    {
        DB::transaction(function () use ($student, $isActive, $actor, $activityLogs): void {
            $oldValues = ['is_active' => $student->is_active];
            $student->update(['is_active' => $isActive]);
            $student->user()->update(['is_active' => $isActive]);
            $activityLogs->record($actor, 'update', 'student', $student, $oldValues, ['is_active' => $isActive]);
        });
    }
}
