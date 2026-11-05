<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Student;
use App\Models\User;
use App\Models\ViolationCategory;
use Illuminate\Database\Eloquent\Model;

class ActivityLogService
{
    /**
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     * @param  array<string, mixed>  $metadata
     */
    public function record(
        User $actor,
        string $action,
        string $category,
        Model $subject,
        ?array $oldValues = null,
        ?array $newValues = null,
        array $metadata = [],
    ): ActivityLog {
        $role = $actor->role()->value('name');
        $oldValues = $this->humanReadableReferences($oldValues);
        $newValues = $this->humanReadableReferences($newValues);

        return ActivityLog::query()->create([
            'user_id' => $actor->id,
            'role_name' => $role ?? 'unknown',
            'action' => $action,
            'category' => $category,
            'subject_type' => $category,
            'subject_id' => $subject->getKey(),
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'before_data' => $oldValues,
            'after_data' => $newValues,
            'metadata' => $metadata,
            'occurred_at' => now('Asia/Jakarta'),
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $values
     * @return array<string, mixed>|null
     */
    private function humanReadableReferences(?array $values): ?array
    {
        if ($values === null) {
            return null;
        }

        if (isset($values['student_id']) && is_numeric($values['student_id'])) {
            $student = Student::query()->with('user:id,name')->find((int) $values['student_id']);
            if ($student !== null) {
                $values['student_id'] = $student->user->name.' ('.$student->nis.')';
            }
        }

        if (isset($values['category_id']) && is_numeric($values['category_id'])) {
            $category = ViolationCategory::query()->find((int) $values['category_id']);
            if ($category !== null) {
                $values['category_id'] = $category->name;
            }
        }

        return $values;
    }
}
