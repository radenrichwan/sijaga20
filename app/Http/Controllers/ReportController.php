<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ReportFilterRequest;
use App\Models\Category;
use App\Models\DutyAttendance;
use App\Models\PointTransaction;
use App\Models\Student;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(ReportFilterRequest $request): View
    {
        $filters = $request->validated();
        $report = $this->buildReport($filters);

        return view('reports.index', [
            ...$report,
            'filters' => $filters,
            'classGroups' => $report['classes'],
            'classes' => Student::query()->distinct()->orderBy('class_name')->pluck('class_name'),
            'majors' => Student::query()->whereNotNull('major')->distinct()->orderBy('major')->pluck('major'),
            'categoryOptions' => Category::query()->orderBy('name')->get(['id', 'name', 'type']),
        ]);
    }

    public function exportCsv(ReportFilterRequest $request): StreamedResponse
    {
        return $this->streamSpreadsheet($request->validated());
    }

    public function exportExcel(ReportFilterRequest $request): StreamedResponse
    {
        return $this->streamSpreadsheet($request->validated());
    }

    public function printPdf(ReportFilterRequest $request): Response
    {
        $filters = $request->validated();
        $report = $this->buildReport($filters);

        return Pdf::loadView('reports.print', [
            ...$report,
            'filters' => $filters,
        ])->setPaper('a4', 'landscape')->download('laporan-poin-'.now()->format('Ymd-His').'.pdf');
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{classes: Collection<int, array<string, mixed>>, categories: Collection<int, Category>, categoryTotals: Collection<int, array<string, int>>, attendanceTotals: Collection<string, int>, totalPoints: int, totalStudents: int}
     */
    private function buildReport(array $filters): array
    {
        $categories = Category::query()
            ->when($filters['category_id'] ?? null, fn (Builder $query, int $id) => $query->whereKey($id))
            ->orderBy('name')
            ->get(['id', 'name', 'type']);

        $students = Student::query()
            ->with('user:id,name,username')
            ->when($filters['class_name'] ?? null, fn (Builder $query, string $className) => $query->where('class_name', $className))
            ->when($filters['major'] ?? null, fn (Builder $query, string $major) => $query->where('major', $major))
            ->when(trim($filters['name'] ?? '') !== '', function (Builder $query) use ($filters): void {
                $name = trim($filters['name']);
                $query->whereHas('user', fn (Builder $users) => $users->where('name', 'like', "%{$name}%"));
            })
            ->when(trim($filters['nisn'] ?? '') !== '', function (Builder $query) use ($filters): void {
                $nisn = trim($filters['nisn']);
                $query->where('nisn', 'like', "%{$nisn}%");
            })
            ->when(trim($filters['search'] ?? '') !== '', function (Builder $query) use ($filters): void {
                $searchTerm = trim($filters['search']);
                $query->where(function (Builder $subquery) use ($searchTerm): void {
                    $subquery->where('nis', 'like', "%{$searchTerm}%")
                        ->orWhere('nisn', 'like', "%{$searchTerm}%")
                        ->orWhereHas('user', fn (Builder $users) => $users->where('name', 'like', "%{$searchTerm}%"));
                });
            })
            ->orderBy('class_name')
            ->orderBy('nis')
            ->get();

        $studentIds = $students->modelKeys();
        $transactions = collect();
        $attendanceCounts = collect();

        if ($studentIds !== []) {
            $transactions = $this->filteredTransactions($filters)
                ->whereIn('point_transactions.student_id', $studentIds)
                ->join('violation_categories', 'violation_categories.id', '=', 'point_transactions.category_id')
                ->select([
                    'point_transactions.student_id',
                    'point_transactions.category_id',
                    'violation_categories.name as category_name',
                    'violation_categories.type as category_type',
                ])
                ->selectRaw('SUM(CASE WHEN point_transactions.counter_entry_for IS NULL THEN 1 ELSE 0 END) AS frequency')
                ->selectRaw('SUM(point_transactions.points) AS points')
                ->groupBy(
                    'point_transactions.student_id',
                    'point_transactions.category_id',
                    'violation_categories.name',
                    'violation_categories.type',
                )
                ->get()
                ->groupBy('student_id');

            $attendanceQuery = DutyAttendance::query()
                ->join('duty_schedules', 'duty_schedules.id', '=', 'duty_attendances.duty_schedule_id')
                ->whereIn('duty_attendances.student_id', $studentIds);
            $this->applyPeriod($attendanceQuery, 'duty_schedules.duty_date', $filters);
            $attendanceCounts = $attendanceQuery
                ->select('duty_attendances.student_id')
                ->selectRaw('COUNT(*) AS frequency')
                ->groupBy('duty_attendances.student_id')
                ->pluck('frequency', 'student_id');
        }

        $rows = $students->map(function (Student $student) use ($transactions, $attendanceCounts): array {
            $entries = $transactions->get($student->id, collect());
            $categoryPoints = [];
            $totalPoints = 0;

            foreach ($entries as $entry) {
                $categoryPoints[$entry->category_id] = [
                    'frequency' => (int) $entry->frequency,
                    'points' => (int) $entry->points,
                ];
                $totalPoints += (int) $entry->points;
            }

            return [
                'student' => $student,
                'categoryPoints' => $categoryPoints,
                'attendanceCount' => (int) $attendanceCounts->get($student->id, 0),
                'totalPoints' => $totalPoints,
            ];
        });

        $groups = $rows->groupBy(fn (array $row): string => $row['student']->class_name)
            ->map(function (Collection $classRows, string $className) use ($categories): array {
                $classCategoryTotals = [];
                foreach ($categories as $category) {
                    $classCategoryTotals[$category->id] = [
                        'frequency' => $classRows->sum(fn (array $row): int => $row['categoryPoints'][$category->id]['frequency'] ?? 0),
                        'points' => $classRows->sum(fn (array $row): int => $row['categoryPoints'][$category->id]['points'] ?? 0),
                    ];
                }

                return [
                    'name' => $className,
                    'rows' => $classRows->values(),
                    'categoryTotals' => $classCategoryTotals,
                    'attendanceCount' => $classRows->sum('attendanceCount'),
                    'totalPoints' => $classRows->sum('totalPoints'),
                ];
            })
            ->values();

        $categoryTotals = [];
        foreach ($categories as $category) {
            $categoryTotals[$category->id] = ['frequency' => 0, 'points' => 0];
            foreach ($rows as $row) {
                $values = $row['categoryPoints'][$category->id] ?? ['frequency' => 0, 'points' => 0];
                $categoryTotals[$category->id]['frequency'] += $values['frequency'];
                $categoryTotals[$category->id]['points'] += $values['points'];
            }
        }

        $attendanceTotals = $groups->mapWithKeys(fn (array $group): array => [
            $group['name'] => $group['rows']->sum('attendanceCount'),
        ]);

        return [
            'classes' => $groups,
            'categories' => $categories,
            'categoryTotals' => collect($categoryTotals),
            'attendanceTotals' => $attendanceTotals,
            'totalPoints' => $rows->sum('totalPoints'),
            'totalStudents' => $rows->count(),
            'selectedCategory' => $filters['category_id'] ?? null
                ? $categories->firstWhere('id', (int) $filters['category_id'])
                : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function streamSpreadsheet(array $filters): StreamedResponse
    {
        $filename = 'laporan-poin-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($filters): void {
            $report = $this->buildReport($filters);
            $output = fopen('php://output', 'w');
            if ($output === false) {
                throw new \RuntimeException('Tidak dapat membuka output CSV.');
            }

            fwrite($output, "\xEF\xBB\xBF");
            $headings = ['Kelas', 'NIS', 'NISN', 'Nama siswa'];
            foreach ($report['categories'] as $category) {
                $headings[] = $category->name.' (frekuensi)';
                $headings[] = $category->name.' (poin)';
            }
            array_push($headings, 'Kehadiran piket', 'Total poin');
            fputcsv($output, $headings);

            foreach ($report['classes'] as $group) {
                foreach ($group['rows'] as $row) {
                    $student = $row['student'];
                    $values = [$group['name'], $student->nis, $student->nisn, $student->user->name];
                    foreach ($report['categories'] as $category) {
                        $metric = $row['categoryPoints'][$category->id] ?? null;
                        $values[] = $metric === null || $metric['frequency'] === 0 ? '-' : $metric['frequency'].' kali';
                        $values[] = $metric === null || $metric['frequency'] === 0 ? '-' : $this->formatPoints($metric['points']);
                    }
                    $values[] = $row['attendanceCount'] === 0 ? '-' : $row['attendanceCount'].' kali';
                    $values[] = $this->formatPoints($row['totalPoints']);
                    fputcsv($output, $values);
                }
            }
            fclose($output);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<PointTransaction>
     */
    private function filteredTransactions(array $filters): Builder
    {
        $query = PointTransaction::query()->where('point_transactions.is_active', true);
        $this->applyPeriod($query, 'point_transactions.transaction_date', $filters);

        return $query
            ->when(
                $filters['category_id'] ?? null,
                fn (Builder $builder, int $id) => $builder->where('point_transactions.category_id', $id),
            )
            ->when(
                $filters['type'] ?? null,
                fn (Builder $builder, string $type) => $builder->where('point_transactions.type', $type),
            );
    }

    /**
     * @param  Builder<Model>  $query
     * @param  array<string, mixed>  $filters
     */
    private function applyPeriod(Builder $query, string $dateColumn, array $filters): void
    {
        $query
            ->when($filters['start_date'] ?? null, fn (Builder $builder, string $date) => $builder->whereDate($dateColumn, '>=', $date))
            ->when($filters['end_date'] ?? null, fn (Builder $builder, string $date) => $builder->whereDate($dateColumn, '<=', $date))
            ->when($filters['year'] ?? null, fn (Builder $builder, int $year) => $builder->whereYear($dateColumn, $year))
            ->when($filters['month'] ?? null, fn (Builder $builder, int $month) => $builder->whereMonth($dateColumn, $month))
            ->when($filters['semester'] ?? null, function (Builder $builder, string $semester) use ($dateColumn): void {
                $builder->whereMonth($dateColumn, $semester === '1' ? '>=' : '<=', $semester === '1' ? 7 : 6);
                $builder->whereMonth($dateColumn, $semester === '1' ? '<=' : '>=', $semester === '1' ? 12 : 1);
            });
    }

    private function formatPoints(int $points): string
    {
        return ($points > 0 ? '+' : '').$points.' poin';
    }
}
