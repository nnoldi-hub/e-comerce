<?php

namespace App\Http\Requests;

use App\Rules\ValidCui;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AddressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['country' => strtoupper((string) ($this->input('country') ?: 'RO'))]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $isCompany = fn () => $this->boolean('is_company');

        return [
            'label' => ['nullable', 'string', 'max:50'],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'string', 'regex:/^[0-9+\s().-]{7,30}$/'],
            'address_line1' => ['required', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:100'],
            'county' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['required', 'string', 'max:20'],
            'country' => ['required', 'string', 'size:2'],
            'is_company' => ['sometimes', 'boolean'],
            'company_name' => [Rule::requiredIf($isCompany), 'nullable', 'string', 'max:255'],
            'cui' => [Rule::requiredIf($isCompany), 'nullable', new ValidCui],
            'reg_com' => ['nullable', 'string', 'max:50'],
            'is_default' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'label' => 'numele adresei',
            'first_name' => 'prenumele',
            'last_name' => 'numele',
            'phone' => 'telefonul',
            'address_line1' => 'adresa',
            'address_line2' => 'detaliile adresei',
            'city' => 'localitatea',
            'county' => 'județul',
            'postal_code' => 'codul poștal',
            'country' => 'țara',
            'company_name' => 'denumirea firmei',
            'cui' => 'CUI-ul',
            'reg_com' => 'numărul de înregistrare',
        ];
    }

    /**
     * Datele firmei se păstrează doar când este bifat „ca firmă”.
     *
     * @return array<string, mixed>
     */
    public function addressData(): array
    {
        $data = $this->validated();

        if (! $this->boolean('is_company')) {
            $data['company_name'] = $data['cui'] = $data['reg_com'] = null;
        }

        $data['is_default'] = $this->boolean('is_default');
        unset($data['is_company']);

        return $data;
    }
}
