<?php

namespace App\Http\Requests\Kick;

use App\Concerns\TimerValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class TimerUpdateRequest extends FormRequest
{
    use TimerValidationRules;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->timerRules();
    }
}
