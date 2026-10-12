<?php

namespace App\Http\Requests\Kick;

use App\Models\Timer;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class TimerOrderUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The new rotation order must list every timer exactly once.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'size:'.Timer::count()],
            'ids.*' => ['integer', 'distinct', 'exists:timers,id'],
        ];
    }
}
