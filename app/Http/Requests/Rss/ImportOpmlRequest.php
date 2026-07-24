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
            // 5 MB, aligned with the OPML-by-URL cap in OpmlController.
            'opml' => ['required', 'file', 'max:5120', 'mimetypes:text/xml,application/xml,text/plain'],
        ];
    }
}
