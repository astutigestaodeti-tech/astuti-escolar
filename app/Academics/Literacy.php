<?php

namespace App\Academics;

use App\Models\Tenant\LiteracyAssessment;
use App\Models\Tenant\LiteracyCycleSetting;
use App\Models\Tenant\SchoolClass;
use App\Models\Tenant\SchoolYear;
use App\Models\Tenant\StudentEnrollment;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class Literacy
{
    /** Sem inferir a série pela idade do estudante. */
    public static function grade(SchoolClass $class): ?int
    {
        $stage = Str::lower(Str::ascii($class->stage));
        $name = Str::lower(Str::ascii($class->name));
        $modality = Str::lower(Str::ascii((string) $class->modality));
        if (! str_contains($stage, 'fundamental') || preg_match('/\beja\b|jovens e adultos|infantil|anos finais/', $stage.' '.$modality)) {
            return null;
        }
        if (! preg_match('/(?:^|\s)([1-9])\s*(?:o|a|º|ª|°)?\s*ano\b/u', $stage, $match)
            && ! preg_match('/^\s*([1-9])\s*(?:o|a|º|ª|°)?(?:\s|$|[-])/u', $name, $match)) {
            return null;
        }

        return in_array((int) $match[1], [1, 2], true) ? (int) $match[1] : null;
    }

    public function settings(string $yearId): array
    {
        $saved = LiteracyCycleSetting::query()->where('school_year_id', $yearId)->first();

        // Preserva a política anterior até a rede deliberar expressamente.
        return [1 => $saved?->retain_first ?? true, 2 => $saved?->retain_second ?? true];
    }

    public function noRetention(SchoolClass $class): bool
    {
        $grade = self::grade($class);

        return $grade !== null && ! $this->settings($class->school_year_id)[$grade];
    }

    public function classes(SchoolYear $year, ?string $schoolId = null): Collection
    {
        return SchoolClass::query()->where('school_year_id', $year->id)->where('status', 'active')
            ->when($schoolId !== null, fn ($query) => $query->where('school_id', $schoolId))
            ->whereHas('school', fn ($query) => $query->where('status', 'active')->whereNull('deleted_at'))
            ->orderBy('name')->get()->filter(fn (SchoolClass $class): bool => self::grade($class) !== null)->values();
    }

    public function enrollments(Collection $classes, bool $withNames = false): Collection
    {
        $classes = $classes->keyBy('id');

        return StudentEnrollment::query()->whereIn('school_class_id', $classes->keys())
            ->where('status', 'active')->whereHas('student', fn ($query) => $query->whereNull('deleted_at'))
            ->when($withNames, fn ($query) => $query->with('student:id,full_name'))
            ->get(['id', 'student_id', 'school_class_id', 'school_id', 'school_year_id'])
            ->filter(function (StudentEnrollment $entry) use ($classes): bool {
                $class = $classes->get($entry->school_class_id);

                return $class !== null && $entry->school_id === $class->school_id && $entry->school_year_id === $class->school_year_id;
            })->values();
    }

    public function assessments(Collection $enrollments): Collection
    {
        $entries = $enrollments->keyBy('id');

        return LiteracyAssessment::query()->whereIn('student_enrollment_id', $entries->keys())->get()
            ->filter(fn (LiteracyAssessment $record): bool => $entries->get($record->student_enrollment_id)?->school_class_id === $record->school_class_id)->values();
    }

    public function distribution(Collection $enrollments, Collection $assessments): array
    {
        $rows = [];
        $byTerm = $assessments->whereIn('student_enrollment_id', $enrollments->pluck('id'))->groupBy('term');
        foreach (range(1, 4) as $term) {
            $items = $byTerm->get($term, collect());
            $writing = array_fill_keys(array_keys(LiteracyAssessment::WRITING), 0);
            $reading = array_fill_keys(array_keys(LiteracyAssessment::READING), 0);
            foreach ($items as $item) {
                $writing[$item->writing]++;
                $reading[$item->reading]++;
            }
            $rows[$term] = [
                'total' => $enrollments->count(),
                'assessed' => $items->count(),
                'missing' => $enrollments->count() - $items->count(),
                'writing' => $writing,
                'reading' => $reading,
                'alphabetic_pct' => $items->isEmpty() ? null : round(100 * $writing['alphabetic'] / $items->count(), 1),
            ];
        }

        return $rows;
    }

    /** Triagem pedagógica, não diagnóstico nem resultado de aprovação. */
    public function needsSupport(LiteracyAssessment $assessment): bool
    {
        return $assessment->writing !== 'alphabetic' || $assessment->reading !== 'fluent_texts';
    }
}
