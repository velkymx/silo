<?php

namespace App\Automation\Actions;

use App\Models\AutomationRule;
use App\Models\Notification;
use Illuminate\Support\Str;

class CreateNotificationAction implements ActionHandler
{
    public function type(): string
    {
        return 'create_notification';
    }

    public function execute(AutomationRule $rule, array $data, array $context): void
    {
        $severity = $data['priority'] ?? $data['severity'] ?? Notification::SEVERITY_NORMAL;
        if (! in_array($severity, [Notification::SEVERITY_LOW, Notification::SEVERITY_NORMAL, Notification::SEVERITY_HIGH], true)) {
            $severity = Notification::SEVERITY_NORMAL;
        }

        $item = $context['item'] ?? null;
        $title = (string) ($data['title'] ?? ($item?->title ? Str::limit($item->title, 80) : 'Update'));
        $body = (string) ($data['body'] ?? $item?->excerpt ?? '');
        $url = (string) ($data['url'] ?? ($item?->url ?? ''));

        $attributes = [
            'user_id' => $rule->user_id ?? $context['user_id'] ?? null,
            'type' => 'automation.notification',
            'severity' => $severity,
            'title' => $title,
            'body' => $body !== '' ? $body : null,
            'url' => $url !== '' ? $url : null,
            'data' => [
                'rule_id' => $rule->id,
                'rule_name' => $rule->name,
                'event' => $context['event_type'] ?? null,
            ],
        ];
        if (! $attributes['user_id']) {
            return; // system rules with no target user — skip
        }
        if ($item) {
            $attributes['source_id'] = $item->id;
            $attributes['source_type'] = $item::class;
        }

        $existing = Notification::query()
            ->where('user_id', $attributes['user_id'])
            ->where('type', $attributes['type'])
            ->where('source_id', $attributes['source_id'] ?? 0)
            ->where('source_type', $attributes['source_type'] ?? '')
            ->whereNull('read_at')
            ->first();
        if ($existing) {
            return;
        }
        Notification::create($attributes);
    }
}
