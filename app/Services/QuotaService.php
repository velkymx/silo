<?php

namespace App\Services;

use App\Models\File;
use App\Models\FileVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class QuotaService
{
    // Bytes currently stored by a user: live + trashed files plus saved versions.
    // Single query via UNION ALL avoids the race window of the old 3-query approach.
    public function usedBytes(int $userId): int
    {
        return (int) cache()->remember("quota.used.{$userId}", 15, function () use ($userId) {
            $row = DB::selectOne('
                SELECT COALESCE(SUM(s), 0) AS total FROM (
                    SELECT size AS s
                        FROM files
                        WHERE owner_id = :uid1 AND is_dir = 0
                    UNION ALL
                    SELECT fv.size AS s
                        FROM file_versions fv
                        JOIN files f ON fv.file_id = f.id
                        WHERE f.owner_id = :uid2
                ) AS combined
            ', ['uid1' => $userId, 'uid2' => $userId]);
            return (int) ($row->total ?? 0);
        });
    }

    public function invalidate(int $userId): void
    {
        cache()->forget("quota.used.{$userId}");
    }

    /**
     * Quota in bytes (0 = unlimited). A per-user override (users.quota_mb)
     * beats the global FILEMANAGER_USER_QUOTA_MB default; null falls through.
     */
    public function quotaBytes(?int $userId = null): int
    {
        $mb = null;
        if ($userId !== null) {
            $mb = User::whereKey($userId)->value('quota_mb');
        }

        return (int) ($mb ?? config('filemanager.user_quota_mb')) * 1024 * 1024;
    }

    // True if storing $additional more bytes would exceed the user's quota.
    public function wouldExceed(int $userId, int $additional): bool
    {
        $quota = $this->quotaBytes($userId);

        return $quota > 0 && ($this->usedBytes($userId) + $additional) > $quota;
    }

    /**
     * Usage summary for the UI.
     *
     * @return array{used:int, quota:int}
     */
    public function summary(int $userId): array
    {
        return ['used' => $this->usedBytes($userId), 'quota' => $this->quotaBytes($userId)];
    }
}
