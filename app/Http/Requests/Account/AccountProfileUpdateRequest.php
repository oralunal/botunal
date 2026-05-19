<?php

namespace App\Http\Requests\Account;

use App\Concerns\AccountProfileValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class AccountProfileUpdateRequest extends FormRequest
{
    use AccountProfileValidationRules;

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->accountProfileRules($this->user()?->id);
    }
}
