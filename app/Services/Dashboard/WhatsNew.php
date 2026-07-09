<?php

namespace App\Services\Dashboard;

use Illuminate\Contracts\Support\Arrayable;

/**
 * The "What's New" card. RSS is the only thing in Silo that changes while the
 * user is away, so this card is deliberately RSS-only: an unread count plus the
 * newest few unread articles. Null (not an empty shell) when nothing is unread.
 *
 * @implements Arrayable<string, mixed>
 */
final readonly class WhatsNew implements Arrayable
{
    /**
     * @param  array<int, array{id: int, title: string, feed: ?string, url: string}>  $articles
     */
    public function __construct(
        public int $unreadCount,
        public array $articles,
        public string $inboxUrl,
    ) {}

    /**
     * @return array{unreadCount: int, articles: array<int, array{id: int, title: string, feed: ?string, url: string}>, inboxUrl: string}
     */
    public function toArray(): array
    {
        return [
            'unreadCount' => $this->unreadCount,
            'articles' => $this->articles,
            'inboxUrl' => $this->inboxUrl,
        ];
    }
}
