<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\StudentRegistrationResource;
use App\Models\StudentRegistration;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminStudentRegistrationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filters = $this->filters($request);
        $registrations = $this->rosterQuery($filters)->paginate(50);

        return response()->json([
            'registrations' => StudentRegistrationResource::collection($registrations->getCollection())->resolve(),
            'total' => $registrations->total(),
            'current_page' => $registrations->currentPage(),
            'last_page' => $registrations->lastPage(),
            'departments' => $this->departments(),
            'sections' => $this->sections($filters['department']),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $filters = $this->filters($request);
        $registrations = $this->rosterQuery($filters)->get();
        $sheets = $this->sheets($registrations);

        return response()->streamDownload(function () use ($sheets): void {
            echo $this->workbook($sheets);
        }, 'student-roster.xml', [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
        ]);
    }

    public function complete(Request $request, StudentRegistration $studentRegistration): JsonResponse
    {
        $studentRegistration = $studentRegistration->markCompletedBy($request->user()?->id);

        return response()->json([
            'message' => 'تم إنهاء الدور.',
            'registration' => new StudentRegistrationResource($studentRegistration),
        ]);
    }

    public function markAbsent(Request $request, StudentRegistration $studentRegistration): JsonResponse
    {
        $studentRegistration = $studentRegistration->markAbsentBy($request->user()?->id);

        return response()->json([
            'message' => 'تم تسجيل الطالب كـ "مش موجود".',
            'registration' => new StudentRegistrationResource($studentRegistration),
        ]);
    }

    public function destroy(StudentRegistration $studentRegistration): JsonResponse
    {
        $studentRegistration->delete();

        return response()->json([
            'message' => 'تم حذف تسجيل الطالب.',
        ]);
    }

    public function resetDay(): JsonResponse
    {
        $deleted = StudentRegistration::resetDay();

        return response()->json([
            'message' => 'تم تصفير أدوار اليوم. الدور هيبدأ من 1.',
            'deleted' => $deleted,
        ]);
    }

    /**
     * @return array{department: string, section_number: int|null, search: string}
     */
    private function filters(Request $request): array
    {
        $validated = $request->validate([
            'department' => ['nullable', 'string', 'max:100'],
            'section_number' => ['nullable', 'integer', 'min:1', 'max:999'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        return [
            'department' => trim((string) ($validated['department'] ?? '')),
            'section_number' => isset($validated['section_number']) ? (int) $validated['section_number'] : null,
            'search' => trim((string) ($validated['search'] ?? '')),
        ];
    }

    /**
     * @param  array{department: string, section_number: int|null, search: string}  $filters
     */
    private function rosterQuery(array $filters): Builder
    {
        return StudentRegistration::query()->matchingRoster(
            $filters['department'],
            $filters['section_number'],
            $filters['search'],
        );
    }

    /**
     * @return list<string>
     */
    private function departments(): array
    {
        return StudentRegistration::query()
            ->select('department')
            ->distinct()
            ->orderBy('department')
            ->pluck('department')
            ->all();
    }

    /**
     * @return list<int>
     */
    private function sections(string $department): array
    {
        return StudentRegistration::query()
            ->when($department !== '', fn (Builder $query): Builder => $query->where('department', $department))
            ->select('section_number')
            ->distinct()
            ->orderBy('section_number')
            ->pluck('section_number')
            ->map(fn (mixed $section): int => (int) $section)
            ->all();
    }

    /**
     * @param  Collection<int, StudentRegistration>  $registrations
     * @return list<array{name: string, rows: list<array{full_name: string, academic_number: string, national_id: string, department: string, section_number: int, student_group: string, turn_number: int, status: string, created_at: string}>}>
     */
    private function sheets(Collection $registrations): array
    {
        if ($registrations->isEmpty()) {
            return [[
                'name' => 'الطلاب',
                'rows' => [],
            ]];
        }

        $usedNames = [];
        $sheets = [];

        foreach ($registrations->groupBy(fn (StudentRegistration $registration): string => $registration->department.'|'.$registration->section_number) as $group) {
            /** @var StudentRegistration $first */
            $first = $group->first();
            $sheets[] = [
                'name' => $this->sheetName($first->department, $first->section_number, $usedNames),
                'rows' => $group->map(fn (StudentRegistration $registration): array => [
                    'full_name' => $registration->full_name,
                    'academic_number' => $registration->academic_number,
                    'national_id' => $registration->national_id,
                    'department' => $registration->department,
                    'section_number' => $registration->section_number,
                    'student_group' => $registration->groupCode(),
                    'turn_number' => $registration->turn_number,
                    'status' => $registration->status?->label() ?? '',
                    'created_at' => $registration->created_at?->timezone('Africa/Cairo')->format('Y-m-d H:i') ?? '',
                ])->all(),
            ];
        }

        return $sheets;
    }

    /**
     * @param  array<string, true>  $usedNames
     */
    private function sheetName(string $department, int $sectionNumber, array &$usedNames): string
    {
        $base = $this->sanitizeSheetName($department.' - '.$sectionNumber);
        $name = $base;
        $suffix = 2;

        while (isset($usedNames[$name])) {
            $extra = ' '.$suffix;
            $name = mb_substr($base, 0, 31 - mb_strlen($extra)).$extra;
            $suffix++;
        }

        $usedNames[$name] = true;

        return $name;
    }

    private function sanitizeSheetName(string $name): string
    {
        $clean = str_replace(['\\', '/', '*', '?', ':', '[', ']'], ' ', $name);
        $collapsed = preg_replace('/\s+/u', ' ', trim($clean));
        $clean = is_string($collapsed) ? trim($collapsed) : trim($clean);

        if ($clean === '') {
            $clean = 'سكشن';
        }

        return mb_substr($clean, 0, 31);
    }

    /**
     * @param  list<array{name: string, rows: list<array{full_name: string, academic_number: string, national_id: string, department: string, section_number: int, student_group: string, turn_number: int, status: string, created_at: string}>}>  $sheets
     */
    private function workbook(array $sheets): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'
            .'<?mso-application progid="Excel.Sheet"?>'
            .'<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet" xmlns:x="urn:schemas-microsoft-com:office:excel">'
            .'<Styles><Style ss:ID="header"><Font ss:Bold="1"/><Interior ss:Color="#E0E7FF" ss:Pattern="Solid"/></Style></Styles>';

        foreach ($sheets as $sheet) {
            $xml .= '<Worksheet ss:Name="'.$this->xml($sheet['name']).'"><Table>';
            $xml .= '<Row>'
                .$this->headerCell('الدور')
                .$this->headerCell('الاسم')
                .$this->headerCell('الرقم الأكاديمي')
                .$this->headerCell('الرقم القومي')
                .$this->headerCell('القسم')
                .$this->headerCell('السكشن')
                .$this->headerCell('المجموعة')
                .$this->headerCell('الحالة')
                .$this->headerCell('تاريخ التسجيل')
                .'</Row>';

            foreach ($sheet['rows'] as $row) {
                $xml .= '<Row>'
                    .$this->textCell((string) $row['turn_number'])
                    .$this->textCell($row['full_name'])
                    .$this->textCell($row['academic_number'])
                    .$this->textCell($row['national_id'])
                    .$this->textCell($row['department'])
                    .$this->textCell((string) $row['section_number'])
                    .$this->textCell($row['student_group'])
                    .$this->textCell($row['status'])
                    .$this->textCell($row['created_at'])
                    .'</Row>';
            }

            $xml .= '</Table><WorksheetOptions xmlns="urn:schemas-microsoft-com:office:excel"><DisplayRightToLeft/></WorksheetOptions></Worksheet>';
        }

        return $xml.'</Workbook>';
    }

    private function headerCell(string $value): string
    {
        return '<Cell ss:StyleID="header"><Data ss:Type="String">'.$this->xml($value).'</Data></Cell>';
    }

    private function textCell(string $value): string
    {
        if (preg_match('/^[=+\-@\t\r]/', $value) === 1) {
            $value = "'".$value;
        }

        return '<Cell><Data ss:Type="String">'.$this->xml($value).'</Data></Cell>';
    }

    private function xml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
