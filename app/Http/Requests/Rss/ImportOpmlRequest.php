<?php

namespace App\Http\Requests\Rss;

use Illuminate\Foundation\Http\FormRequest;

class ImportOpmlRequest extends FormRequest
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
            'opml' => ['required', 'file', 'max:512', 'mimetypes:text/xml,application/xml,text/plain'],
        ];
    }
}
