<?php

declare(strict_types=1);

namespace App\Services\Directors;

use App\Models\DirectorAcademicProfile;
use App\Models\User;

/**
 * Upserts director academic profiles from admin forms / whitelist creation.
 * Keeps list-parsing rules in one place for UI (newline-separated) and API arrays.
 */
final class DirectorAcademicProfileWriter
{
    /**
     * @param  array{
     *     areas?: string|null,
     *     research_lines?: list<string>|string|null,
     *     technologies?: list<string>|string|null,
     *     methodologies?: list<string>|string|null,
     *     academic_experience?: string|null,
     *     years_of_experience?: int|null
     * }  $data
     */
    public function upsert(User $user, array $data): DirectorAcademicProfile
    {
        if (array_key_exists('areas', $data)) {
            $areas = $data['areas'];
            $user->areas = is_string($areas) && trim($areas) !== '' ? trim($areas) : null;
            $user->save();
        }

        $profile = DirectorAcademicProfile::query()->firstOrNew(['user_id' => $user->id]);

        if (array_key_exists('research_lines', $data)) {
            $profile->research_lines = $this->normalizeList($data['research_lines']);
        }
        if (array_key_exists('technologies', $data)) {
            $profile->technologies = $this->normalizeList($data['technologies']);
        }
        if (array_key_exists('methodologies', $data)) {
            $profile->methodologies = $this->normalizeList($data['methodologies']);
        }
        if (array_key_exists('academic_experience', $data)) {
            $text = $data['academic_experience'];
            $profile->academic_experience = is_string($text) && trim($text) !== '' ? trim($text) : null;
        }
        if (array_key_exists('years_of_experience', $data)) {
            $years = $data['years_of_experience'];
            $profile->years_of_experience = $years === null || $years === ''
                ? null
                : max(0, min(80, (int) $years));
        }

        // Ensure JSON columns are arrays even on first create with partial payload.
        $profile->research_lines ??= [];
        $profile->technologies ??= [];
        $profile->methodologies ??= [];

        $profile->save();

        return $profile->fresh();
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(User $user): array
    {
        $user->loadMissing('academicProfile');
        $profile = $user->academicProfile;

        return [
            'user_id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'areas' => (string) ($user->areas ?? ''),
            'areas_list' => $this->normalizeList($user->areas),
            'research_lines' => $this->normalizeList($profile?->research_lines),
            'technologies' => $this->normalizeList($profile?->technologies),
            'methodologies' => $this->normalizeList($profile?->methodologies),
            'academic_experience' => (string) ($profile?->academic_experience ?? ''),
            'years_of_experience' => $profile?->years_of_experience,
        ];
    }

    /**
     * @param  mixed  $value
     * @return list<string>
     */
    public function normalizeList(mixed $value): array
    {
        if (is_string($value)) {
            $parts = preg_split('/\r\n|\r|\n|,/', $value) ?: [];
        } elseif (is_array($value)) {
            $parts = $value;
        } else {
            return [];
        }

        $items = [];
        foreach ($parts as $part) {
            if (! is_string($part)) {
                continue;
            }
            $trimmed = trim($part);
            if ($trimmed !== '') {
                $items[] = $trimmed;
            }
        }

        return array_values(array_unique($items));
    }
}
