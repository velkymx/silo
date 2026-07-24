<?php

namespace App\Http\Requests\Rss;

use Illuminate\Foundation\Http\FormRequest;

class UpdateFeedRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('feed'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:120'],
            'url' => ['sometimes', 'required', 'url:http,https', 'max:2048'],
            'folder' => ['sometimes', 'nullable', 'string', 'max:60'],
            'enabled' => ['sometimes', 'boolean'],
            'refresh_interval_minutes' => ['sometimes', 'nullable', 'integer', 'min:5', 'max:1440'],
        ];
    }
}
