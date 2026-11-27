<?php

namespace App\Http\Requests;

use App\Rules\FutureKolkataDateTime;
use App\Services\Payments\BhdAmount;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
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
                    } elseif (config('payments.driver') === 'afs') {
                        BhdAmount::gatewayAmount((string) $value);
                    }
                } catch (InvalidArgumentException $exception) {
                    if (str_starts_with($exception->getMessage(), 'AFS accepts')) {
                        $fail($exception->getMessage());

                        return;
                    }

                    $fail('Enter an amount with no more than three decimal places.');
                }
            }],
            'description' => ['required', 'string', 'max:255'],
            'validity_days' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:30'],
            'expiry_option' => ['nullable', Rule::in(['1_hour', '6_hours', '12_hours', '24_hours', '2_days', '3_days', '7_days', 'custom'])],
            'custom_expires_at' => ['nullable', 'required_if:expiry_option,custom', 'date_format:Y-m-d\TH:i', new FutureKolkataDateTime],
            'due_at' => ['nullable', 'date_format:Y-m-d\TH:i'],
            'confirmed' => ['accepted'],
            'replace_existing' => ['sometimes', 'boolean'],
        ];
    }
}
