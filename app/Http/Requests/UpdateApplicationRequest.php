<?php

namespace App\Http\Requests;

use App\Models\Application;
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
        $application = $this->route('application');
        $service = $application instanceof Application
            ? (config('applications.services')[$application->service_type] ?? [])
            : [];
        $fields = $service['fields'] ?? [];

        $rules = [
            'status' => ['sometimes', 'required', Rule::in(array_keys(config('applications.statuses')))],
            'admin_notes' => ['sometimes', 'nullable', 'string', 'max:20000'],
            'status_note' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'full_name' => ['sometimes', 'required', 'string', 'max:150'],
            'nationality' => ['sometimes', 'required', 'string', 'max:100'],
            'date_of_birth' => ['sometimes', 'nullable', 'date', 'before_or_equal:today', 'after:1900-01-01'],
            'current_country' => ['sometimes', 'required', 'string', 'max:100'],
            'current_city' => ['sometimes', 'nullable', 'string', 'max:100'],
            'phone' => ['sometimes', 'required', 'string', 'max:30', 'regex:/^\+?[0-9 ()\-.]{7,30}$/'],
            'email' => ['sometimes', 'required', 'email:rfc', 'max:254'],
            'preferred_contact_method' => ['sometimes', 'nullable', Rule::in(['whatsapp', 'phone', 'email'])],
            'details' => $fields ? ['sometimes', 'array:'.implode(',', array_keys($fields))] : ['sometimes', 'array', 'max:0'],
        ];

        foreach ($fields as $key => $field) {
            $rule = ['nullable'];
            $rule = array_merge($rule, match ($field['type']) {
                'select' => ['string', Rule::in($field['options'])],
                'checkboxes' => ['array', 'min:1', 'max:'.count($field['options'])],
                'number' => ['integer', 'min:'.$field['min'], 'max:'.$field['max']],
                'date' => ['date'],
                'textarea' => ['string', 'max:5000'],
                default => ['string', 'max:255'],
            });
            $rules['details.'.$key] = $rule;

            if ($field['type'] === 'checkboxes') {
                $rules['details.'.$key.'.*'] = ['string', 'distinct', Rule::in($field['options'])];
            }
        }

        return $rules;
    }

    public function attributes(): array
    {
        $attributes = [];
        foreach (config('applications.services') as $service) {
            foreach ($service['fields'] as $key => $field) {
                $attributes['details.'.$key] = strtolower($field['label']);
            }
        }

        return $attributes;
    }
}
