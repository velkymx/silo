<?php

namespace App\Automation\Actions;

use App\Automation\EventDispatcher;
use App\Models\AutomationRule;
use App\Models\RssItem;

class MarkStarredAction implements ActionHandler
{
    public function type(): string
    {
        return 'mark_starred';
    }

    public function execute(AutomationRule $rule, array $data, array $context): void
    {
        $item = $context['item'] ?? null;
        if (! $item instanceof RssItem) {
            return;
        }
        if ($item->is_starred) {
            return;
        }
        $item->toggleStar();
        app(EventDispatcher::class)->dispatch('rss.item.starred', $item->user_id, [
            'item_id' => $item->id,
            'feed_id' => $item->feed_id,
            'title' => $item->title,
            'url' => $item->url,
            'starred' => true,
            'starred_at' => $item->starred_at?->toIso8601String(),
        ]);
    }
}
