<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FileTreeRequest extends FormRequest
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
            'parent' => ['nullable', 'integer', 'exists:files,id'],
        ];
    }
}
