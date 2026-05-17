<?php

namespace App\Http\Requests\Kick;

use App\Concerns\ModerationValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class BanRequest extends FormRequest
{
    use ModerationValidationRules;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->banRules();
    }
}
