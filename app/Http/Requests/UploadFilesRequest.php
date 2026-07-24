<?php

namespace App\Http\Requests;

use App\Support\Uploads;
use Illuminate\Foundation\Http\FormRequest;

class UploadFilesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'files.*' => ['required', 'file', 'max:'.Uploads::maxKb()],
            'parent_id' => ['nullable', 'integer', 'exists:files,id'],
        ];
    }
}
