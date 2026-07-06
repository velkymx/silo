<?php

namespace Database\Seeders;

use App\Models\WorkflowTemplate;
use Illuminate\Database\Seeder;

/**
 * Opinionated starter templates users can clone. Add a row here when
 * a new event source ships, or when a popular automation pattern
 * emerges in support.
 */
class WorkflowTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            [
                'slug' => 'star-security-posts',
                'name' => 'Star security posts',
                'description' => 'Star any RSS article whose title mentions "security".',
                'icon' => 'shield-lock-fill',
                'trigger_event' => 'rss.item.created',
                'sort_order' => 10,
                'conditions_json' => ['title_contains' => 'security'],
                'actions_json' => [
                    ['type' => 'mark_starred', 'data' => []],
                ],
            ],
            [
                'slug' => 'high-priority-laravel-news',
                'name' => 'High-priority Laravel news',
                'description' => 'Notify + star any Laravel News post that mentions security or release.',
                'icon' => 'megaphone-fill',
                'trigger_event' => 'rss.item.created',
                'sort_order' => 20,
                'conditions_json' => [
                    'feed_title_contains' => 'Laravel',
                    'title_contains' => 'security',
                ],
                'actions_json' => [
                    ['type' => 'mark_starred', 'data' => []],
                    ['type' => 'create_notification', 'data' => ['priority' => 'high', 'title' => 'New Laravel security post']],
                ],
            ],
            [
                'slug' => 'save-research-to-bookmarks',
                'name' => 'Save research feeds to bookmarks',
                'description' => 'Bookmark any item from a feed whose title contains "research".',
                'icon' => 'bookmark-star',
                'trigger_event' => 'rss.item.created',
                'sort_order' => 30,
                'conditions_json' => ['title_contains' => 'research'],
                'actions_json' => [
                    ['type' => 'save_bookmark', 'data' => ['category' => 'Research']],
                ],
            ],
        ];

        foreach ($templates as $row) {
            WorkflowTemplate::updateOrCreate(['slug' => $row['slug']], $row);
        }
    }
}
