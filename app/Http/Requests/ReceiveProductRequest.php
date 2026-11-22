<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReceiveProductRequest extends FormRequest
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
            'qty' => ['required', 'integer', 'min:1', 'max:9999'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->exists('qty') || $this->input('qty') === null || $this->input('qty') === '') {
            $this->merge(['qty' => 1]);
        }
    }
}
