<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MoveCopyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'target_id' => ['nullable', 'integer', 'exists:files,id'],
        ];
    }
}
