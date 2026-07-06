<?php

namespace App\Http\Requests\Rss;

use Illuminate\Foundation\Http\FormRequest;

class ImportOpmlFromUrlRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'url' => ['required', 'string', 'url:http,https', 'max:2048'],
        ];
    }
}
