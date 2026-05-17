<?php

namespace App\Http\Requests\Kick;

use App\Concerns\CommandValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CommandStoreRequest extends FormRequest
{
    use CommandValidationRules;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->commandRules();
    }
}
