<?php

namespace App\Http\Requests\Kick;

use App\Concerns\WikiValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class WikiStoreRequest extends FormRequest
{
    use WikiValidationRules;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->wikiRules();
    }
}
