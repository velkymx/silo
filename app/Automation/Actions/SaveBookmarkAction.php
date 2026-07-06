<?php

namespace App\Automation\Actions;

use App\Models\AutomationRule;
use App\Models\Bookmark;
use App\Models\RssItem;
use Illuminate\Support\Str;

class SaveBookmarkAction implements ActionHandler
{
    public function type(): string
    {
        return 'save_bookmark';
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
        $url = (string) ($data['url'] ?? $item->url);
        if ($url === '') {
            return;
        }
        $title = (string) ($data['title'] ?? Str::limit($item->title, 120, ''));
        $category = isset($data['category']) ? Str::limit((string) $data['category'], 60, '') : 'RSS';

        $existing = Bookmark::where('owner_id', $rule->user_id)
            ->where('url', $url)
            ->first();
        if ($existing) {
            return;
        }
        Bookmark::create([
            'owner_id' => $rule->user_id,
            'title' => $title,
            'url' => $url,
            'description' => $item->excerpt,
            'icon' => 'rss',
            'category' => $category,
            'shared' => false,
            'feed_url' => $context['feed_url'] ?? null,
        ]);
    }
}
