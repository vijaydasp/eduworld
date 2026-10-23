<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage-settings');
    }

    public function rules(): array
    {
        $rules = [];
        foreach (config('website') as $key => $field) {
            $rules[$key] = ['nullable', ...explode('|', $field['rule'])];
        }
        foreach (['company_name', 'address', 'email', 'phone', 'whatsapp', 'currency'] as $key) {
            $rules[$key][0] = 'required';
        }
        $rules['logo'] = ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'];
        $rules['favicon'] = ['nullable', 'image', 'mimes:png', 'max:512'];

        return $rules;
    }

    public function after(): array
    {
        return [function ($validator) {
            if ($this->filled('map_url') && ! in_array(parse_url($this->input('map_url'), PHP_URL_HOST), ['www.google.com', 'maps.google.com'], true)) {
                $validator->errors()->add('map_url', 'Use a Google Maps embed URL.');
            }
        }];
    }
}
