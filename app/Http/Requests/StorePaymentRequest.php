<?php

namespace App\Http\Requests;

use App\Services\Payments\BhdAmount;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class StorePaymentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'customer_name' => ['required', 'string', 'max:150'],
            'phone' => ['required', 'string', 'regex:/\A[+0-9() .-]{7,30}\z/'],
            'email' => ['nullable', 'email:rfc', 'max:255'],
            'entered_reference' => ['nullable', 'string', 'max:100'],
            'service' => ['required', Rule::in(array_keys(config('applications.services')))],
            'amount' => ['required', 'string', function (string $attribute, mixed $value, Closure $fail): void {
                try {
                    $minor = BhdAmount::minorUnits((string) $value);
                    if ($minor < 1 || $minor > BhdAmount::minorUnits(config('payments.maximum_amount'))) {
                        $fail('Enter a BHD amount between 0.001 and '.config('payments.maximum_amount').'.');
                    }
                } catch (InvalidArgumentException) {
                    $fail('Enter an amount with no more than three decimal places.');
                }
            }],
            'remarks' => ['nullable', 'string', 'max:2000'],
            'terms' => ['accepted'],
            'website' => ['nullable', 'string', 'max:0'],
        ];
    }
}
