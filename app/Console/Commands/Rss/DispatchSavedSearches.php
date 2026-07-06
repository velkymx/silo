<?php

namespace App\Console\Commands\Rss;

use App\Models\Notification;
use App\Models\SavedSearch;
use App\Services\PlatformSearch;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Periodic worker for saved-search notifications. Walks every
 * SavedSearch, runs the query through PlatformSearch, and pushes a
 * Notification row for the owner whenever the result count is
 * higher than the previous run (a strict-greater comparison so
 * steady-state searches don't spam).
 *
 * Idempotent and best-effort: a failure on one search is logged
 * and skipped so the rest of the batch still runs.
 */
#[Signature('rss:dispatch-saved-searches')]
#[Description('Run all saved searches and notify owners of new results.')]
class DispatchSavedSearches extends Command
{
    public function handle(PlatformSearch $search): int
    {
        $count = 0;
        $queries = SavedSearch::query()->get();
        foreach ($queries as $row) {
            try {
                $this->runOne($row, $search);
                $count++;
            } catch (\Throwable $e) {
                $this->error("[saved-search:{$row->id}] {$e->getMessage()}");
            }
        }
        $this->info("Ran {$count} saved search(es).");

        return self::SUCCESS;
    }

    private function runOne(SavedSearch $row, PlatformSearch $search): void
    {
        $params = $row->params ?? [];
        $url = $row->isGlobal() ? '/search' : '/';
        $queryParams = $row->routeParams();

        // Skip searches that have never been run — first run should never
        // notify (it'd flood the user with a "you have 47 results" right
        // after saving the search).
        if ($row->last_run_at === null) {
            $results = $search->search($row->owner_id, $queryParams['q'] ?? '');
            $total = array_sum(array_map('count', $results));
            $row->update([
                'last_run_at' => now(),
                'last_result_count' => $total,
            ]);

            return;
        }

        if ($row->isGlobal()) {
            $results = $search->search($row->owner_id, $queryParams['q'] ?? '');
            $total = array_sum(array_map('count', $results));
        } else {
            // File smart folders: re-issue the search through the file scope.
            $total = \App\Models\File::query()
                ->where('owner_id', $row->owner_id)
                ->where(function ($q) use ($params) {
                    if (! empty($params['search'])) {
                        $q->where('name', 'like', "%{$params['search']}%");
                    }
                })
                ->count();
        }

        $previous = (int) $row->last_result_count;
        if ($total > $previous) {
            Notification::create([
                'user_id' => $row->owner_id,
                'type' => 'saved_search.new_results',
                'severity' => 'normal',
                'title' => "New results for “{$row->name}”",
                'body' => "{$total} result(s) — was {$previous} on the last run.",
                'url' => $url.(empty($queryParams) ? '' : '?'.http_build_query($queryParams)),
            ]);
        }
        $row->update([
            'last_run_at' => now(),
            'last_result_count' => $total,
        ]);
    }
}
