<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-applications') ?? false;
    }

    public function rules(): array
    {
        return ['status' => ['required', Rule::in(array_keys(config('applications.statuses')))], 'admin_notes' => ['nullable', 'string', 'max:20000'], 'status_note' => ['nullable', 'string', 'max:5000']];
    }
}
