<?php

namespace App\Http\Requests;

use App\Services\Payments\BhdAmount;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class StoreStandalonePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-payments') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'customer_name' => ['required', 'string', 'max:150'],
            'customer_email' => ['required', 'email:rfc', 'max:254'],
            'customer_phone' => ['nullable', 'string', 'max:30'],
            'amount' => ['required', 'string', function (string $attribute, mixed $value, Closure $fail): void {
                try {
                    $minor = BhdAmount::minorUnits((string) $value);
                    if ($minor < 1 || $minor > BhdAmount::minorUnits((string) config('payments.maximum_amount'))) {
                        $fail('Enter a BHD amount between 0.001 and '.config('payments.maximum_amount').'.');
                    }
                } catch (InvalidArgumentException) {
                    $fail('Enter an amount with no more than three decimal places.');
                }
            }],
            'currency' => ['nullable', Rule::in(['BHD'])],
            'description' => ['required', 'string', 'max:255'],
            'validity_days' => ['required', 'integer', 'min:1', 'max:30'],
            'confirmed' => ['accepted'],
            // Standalone requests are never linked to an application by browser input.
            'application_id' => ['prohibited'],
        ];
    }
}
