<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class SaveUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage-users');
    }

    public function rules(): array
    {
        return ['name' => ['required', 'string', 'max:120'], 'email' => ['required', 'email', 'max:190', Rule::unique('users')->ignore($this->route('user'))],
            'role' => ['required', Rule::in(['super_admin', 'admin', 'content_manager'])],
            'is_active' => ['required', 'boolean'], 'password' => [$this->route('user') ? 'nullable' : 'required', 'confirmed', Password::defaults()]];
    }
}
