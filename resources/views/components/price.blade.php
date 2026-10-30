@props(['amount', 'currency' => 'RON'])
@php($minor = $amount instanceof \App\ValueObjects\Money ? $amount->amount() : (int) $amount)
<span {{ $attributes->class(['whitespace-nowrap tabular-nums']) }}>{{ number_format($minor / 100, 2, ',', '.') }} {{ $currency }}</span>
