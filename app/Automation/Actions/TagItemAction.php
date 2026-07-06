<?php

namespace App\Automation\Actions;

use App\Models\AutomationRule;
use App\Models\RssItem;
use App\Models\Tag;
use Illuminate\Support\Facades\DB;

class TagItemAction implements ActionHandler
{
    public function type(): string
    {
        return 'tag_item';
    }

    public function execute(AutomationRule $rule, array $data, array $context): void
    {
        $item = $context['item'] ?? null;
        if (! $item instanceof RssItem) {
            return;
        }
        if (! $rule->user_id) {
            return;
        }
        $tags = collect($data['tags'] ?? [])->merge([$data['tag'] ?? null])
            ->filter(fn ($t) => is_string($t) && trim($t) !== '')
            ->map(fn ($t) => trim($t))
            ->unique()
            ->values();
        if ($tags->isEmpty()) {
            return;
        }
        $fileRow = $item->file;
        if (! $fileRow) {
            return;
        }
        $ids = $tags->map(function (string $name) use ($rule) {
            return Tag::firstOrCreate(
                ['user_id' => $rule->user_id, 'name' => $name],
                ['color' => null],
            )->id;
        });
        DB::table('file_tag')->upsert(
            $ids->map(fn ($id) => ['file_id' => $fileRow->id, 'tag_id' => $id])->all(),
            ['file_id', 'tag_id'],
        );
    }
}
