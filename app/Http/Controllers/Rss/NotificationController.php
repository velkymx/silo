<?php

namespace App\Http\Controllers\Rss;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\Request;
use Inertia\Inertia;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $userId = auth()->id();
        $filter = $request->string('filter')->lower()->toString();

        $rows = Notification::ownedBy($userId)
            ->when($filter === 'unread', fn ($q) => $q->unread())
            ->when($filter === 'high', fn ($q) => $q->where('severity', Notification::SEVERITY_HIGH))
            ->orderByDesc('created_at')
            ->limit(100)
            ->get()
            ->map(fn (Notification $n) => [
                'id' => $n->id,
                'type' => $n->type,
                'severity' => $n->severity,
                'title' => $n->title,
                'body' => $n->body,
                'url' => $n->url,
                'read_at' => optional($n->read_at)->toIso8601String(),
                'created_at' => optional($n->created_at)->toIso8601String(),
            ])
            ->values();

        $unread = Notification::ownedBy($userId)->unread()->count();

        return Inertia::render('Rss/Notifications', [
            'notifications' => $rows,
            'unread' => $unread,
            'filters' => ['filter' => $filter ?: null],
        ]);
    }

    public function markRead(Notification $notification)
    {
        $this->authorize('update', $notification);
        $notification->markRead();

        return back();
    }

    public function markAllRead()
    {
        $count = 0;
        Notification::ownedBy(auth()->id())->unread()->each(function (Notification $n) use (&$count) {
            $n->markRead();
            $count++;
        });

        return back()->with('success', "Marked {$count} notification(s) as read.");
    }
}
