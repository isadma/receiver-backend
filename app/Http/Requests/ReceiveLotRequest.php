<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReceiveLotRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'shop' => ['required', 'string', Rule::exists('shops', 'slug')],
            'box' => ['required', 'string', 'max:64'],
            'allow_repeat' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'shop.required' => 'Выберите магазин.',
            'shop.exists' => 'Неизвестный магазин.',
            'box.required' => 'Введите номер короба.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->box) || is_numeric($this->box)) {
            $this->merge(['box' => trim((string) $this->box)]);
        }
    }
}
