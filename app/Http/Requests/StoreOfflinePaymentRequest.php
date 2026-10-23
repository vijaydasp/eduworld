<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;

class StoreOfflinePaymentRequest extends StorePaymentRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('manage-settings') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = parent::rules();
        unset($rules['terms'], $rules['website']);
        $rules['amount_received'] = $rules['amount'];
        $rules['offline_reference'] = ['required', 'string', 'max:150'];
        $rules['application_reference'] = ['nullable', 'string', 'exists:applications,reference_number'];
        $rules['remarks'] = ['required', 'string', 'min:5', 'max:2000'];

        return $rules;
    }
}
