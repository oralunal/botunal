<?php

namespace App\Http\Requests\Account;

use App\Concerns\MemberMessageValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class MemberMessageStoreRequest extends FormRequest
{
    use MemberMessageValidationRules;

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
        return $this->memberMessageRules();
    }
}
