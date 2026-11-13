<?php

namespace App\Http\Requests;

use App\Services\Payments\BhdAmount;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use InvalidArgumentException;

class StoreApplicationFeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-applications') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
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
            'description' => ['required', 'string', 'max:255'],
            'validity_days' => ['sometimes', 'integer', 'min:1', 'max:30'],
            'confirmed' => ['accepted'],
            'replace_existing' => ['sometimes', 'boolean'],
        ];
    }
}
