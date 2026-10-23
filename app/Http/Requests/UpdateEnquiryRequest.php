<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEnquiryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-enquiries') ?? false;
    }

    public function rules(): array
    {
        return ['status' => ['required', Rule::in(['new', 'contacted', 'resolved'])], 'admin_notes' => ['nullable', 'string', 'max:20000']];
    }
}
