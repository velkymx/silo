<?php

namespace App\Services;

use App\Models\File;
use App\Models\FileVersion;

class QuotaService
{
    // Bytes currently stored by a user: live + trashed files plus saved versions.
    public function usedBytes(int $userId): int
    {
        $fileIds = File::withTrashed()->where('owner_id', $userId)->pluck('id');

        $files = File::withTrashed()->where('owner_id', $userId)
            ->where('is_dir', false)->sum('size');
        $versions = FileVersion::whereIn('file_id', $fileIds)->sum('size');

        return (int) ($files + $versions);
    }

    // Quota in bytes (0 = unlimited).
    public function quotaBytes(): int
    {
        return (int) config('filemanager.user_quota_mb') * 1024 * 1024;
    }

    // True if storing $additional more bytes would exceed the user's quota.
    public function wouldExceed(int $userId, int $additional): bool
    {
        $quota = $this->quotaBytes();

        return $quota > 0 && ($this->usedBytes($userId) + $additional) > $quota;
    }

    /**
     * Usage summary for the UI.
     *
     * @return array{used:int, quota:int}
     */
    public function summary(int $userId): array
    {
        return ['used' => $this->usedBytes($userId), 'quota' => $this->quotaBytes()];
    }
}
