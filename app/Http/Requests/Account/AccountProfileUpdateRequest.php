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

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'required' => ':attribute alanı zorunludur.',
            'email' => 'Geçerli bir :attribute adresi giriniz.',
            'unique' => 'Bu :attribute zaten kullanılıyor.',
            'string' => ':attribute metin olmalıdır.',
            'max' => ':attribute en fazla :max karakter olabilir.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'first_name' => 'ad',
            'last_name' => 'soyad',
            'email' => 'e-posta',
            'phone' => 'telefon',
            'instagram' => 'Instagram',
            'twitter' => 'Twitter',
        ];
    }
}
