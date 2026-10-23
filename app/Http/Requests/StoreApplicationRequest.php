<?php

namespace App\Http\Requests;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $serviceType = $this->input('service_type');
        $service = is_string($serviceType) ? (config('applications.services')[$serviceType] ?? []) : [];
        $fields = $service['fields'] ?? [];
        $uploads = $service['uploads'] ?? [];
        $rules = [
            'service_type' => ['required', 'string', Rule::in(array_keys(config('applications.services')))],
            'full_name' => ['required', 'string', 'max:150'],
            'nationality' => ['required', 'string', 'max:100'],
            'date_of_birth' => ['nullable', 'date', 'before_or_equal:today', 'after:1900-01-01'],
            'current_country' => ['required', 'string', 'max:100'],
            'current_city' => ['nullable', 'string', 'max:100'],
            'phone' => ['required', 'string', 'max:30', 'regex:/^\\+?[0-9 ()\\-.]{7,30}$/'],
            'email' => ['required', 'email:rfc', 'max:254'],
            'preferred_contact_method' => ['nullable', Rule::in(['whatsapp', 'phone', 'email'])],
            'consent_accuracy' => ['required', 'accepted'],
            'consent_processing' => ['required', 'accepted'],
            'website' => ['nullable', 'max:0'],
            'details' => ['required', 'array:'.implode(',', array_keys($fields))],
            'documents' => $uploads ? ['nullable', 'array:'.implode(',', array_keys($uploads))] : ['prohibited'],
        ];
        foreach ($fields as $key => $field) {
            $rule = [($field['required'] ?? false) ? 'required' : 'nullable'];
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
        if ($this->input('service_type') === 'supporting_documents' && $this->filled('details.travel_start_date')) {
            $rules['details.travel_end_date'][] = 'after_or_equal:details.travel_start_date';
        }
        foreach (array_keys($uploads) as $kind) {
            $rules['documents.'.$kind] = ['nullable', 'array', 'max:5'];
            $rules['documents.'.$kind.'.*'] = [
                'required', 'file', 'max:'.config('applications.max_document_kb'),
                'extensions:pdf,jpg,jpeg,png', 'mimetypes:application/pdf,image/jpeg,image/png',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! $value instanceof UploadedFile || ! $value->isValid()) {
                        $fail('This document could not be uploaded. Please try again.');

                        return;
                    }
                    $mime = match (strtolower($value->getClientOriginalExtension())) {
                        'pdf' => 'application/pdf', 'jpg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', default => null,
                    };
                    if ($mime !== $value->getMimeType()) {
                        $fail('The document contents must match its PDF, JPG or PNG extension.');
                    }
                },
            ];
        }

        return $rules;
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if (count(Arr::flatten($this->allFiles())) > 12) {
                $validator->errors()->add('documents', 'Upload no more than 12 documents per application.');
            }
        }];
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
