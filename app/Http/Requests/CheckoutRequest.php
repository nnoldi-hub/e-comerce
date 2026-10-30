<?php

namespace App\Http\Requests;

use App\Domain\Checkout\DTOs\PlaceOrderData;
use App\Rules\ValidCui;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('country')) {
            $this->merge(['country' => strtoupper((string) $this->input('country'))]);
        }

        if ($this->filled('coupon_code')) {
            $this->merge(['coupon_code' => trim((string) $this->input('coupon_code'))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $isCompany = fn () => $this->boolean('is_company');

        return [
            'email' => ['required', 'email:rfc', 'max:255'],
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
            'shipping_method_id' => ['required', 'integer', 'exists:shipping_methods,id'],
            'payment_method' => ['required', Rule::in(['cod', 'card'])],
            'coupon_code' => ['nullable', 'string', 'max:64'],
            'customer_note' => ['nullable', 'string', 'max:1000'],
            'idempotency_key' => ['required', 'uuid'],
            'terms' => ['accepted'],
        ];
    }

    /**
     * Numele câmpurilor în mesajele de eroare (altfel apare „shipping method id”).
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'email' => 'adresa de email',
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
            'shipping_method_id' => 'metoda de livrare',
            'payment_method' => 'metoda de plată',
            'coupon_code' => 'codul de reducere',
            'customer_note' => 'observațiile',
            'idempotency_key' => 'cheia comenzii',
            'terms' => 'acceptarea termenilor',
        ];
    }

    public function toData(?int $userId): PlaceOrderData
    {
        $v = $this->validated();

        $address = [
            'first_name' => $v['first_name'],
            'last_name' => $v['last_name'],
            'phone' => $v['phone'],
            'address_line1' => $v['address_line1'],
            'address_line2' => $v['address_line2'] ?? null,
            'city' => $v['city'],
            'county' => $v['county'] ?? null,
            'postal_code' => $v['postal_code'],
            'country' => strtoupper($v['country']),
        ];

        $billing = $address;

        if ($this->boolean('is_company')) {
            $billing['company_name'] = $v['company_name'] ?? null;
            $billing['cui'] = $v['cui'] ?? null;
            $billing['reg_com'] = $v['reg_com'] ?? null;
        }

        return new PlaceOrderData(
            email: $v['email'],
            billingAddress: $billing,
            shippingAddress: $address,
            shippingMethodId: (int) $v['shipping_method_id'],
            paymentMethod: $v['payment_method'],
            idempotencyKey: $v['idempotency_key'],
            couponCode: ($v['coupon_code'] ?? '') !== '' ? $v['coupon_code'] : null,
            customerNote: $v['customer_note'] ?? null,
            userId: $userId,
        );
    }
}
