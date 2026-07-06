<?php

namespace App\Http\Requests\Rss;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFeedRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Models\RssFeed::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:120'],
            'url' => [
                'required', 'url:http,https', 'max:2048',
                Rule::unique('rss_feeds', 'url')->where('user_id', $this->user()->id),
            ],
            'folder' => ['nullable', 'string', 'max:60'],
            'enabled' => ['boolean'],
            'refresh_interval_minutes' => ['nullable', 'integer', 'min:5', 'max:1440'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function validatedFeedAttributes(): array
    {
        $v = $this->validated();

        return [
            'title' => $v['title'],
            'url' => $v['url'],
            'folder' => $v['folder'] ?? null,
            'enabled' => $v['enabled'] ?? true,
            'refresh_interval_minutes' => (int) ($v['refresh_interval_minutes'] ?? 60),
        ];
    }
}
