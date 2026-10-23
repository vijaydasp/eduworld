<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:150'],
            'phone' => ['required', 'string', 'max:30', 'regex:/^\\+?[0-9 ()\\-.]{7,30}$/'],
            'email' => ['nullable', 'email:rfc', 'max:254'],
            'service_type' => ['required', 'string', Rule::in(array_keys(config('applications.services')))],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
            'website' => ['nullable', 'max:0'],
        ];
    }
}
