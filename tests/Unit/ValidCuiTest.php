<?php

use App\Rules\ValidCui;

function cuiFails(string $value): bool
{
    $failed = false;

    (new ValidCui)->validate('cui', $value, function () use (&$failed) {
        $failed = true;
    });

    return $failed;
}

it('acceptă CUI-uri cu cifră de control corectă', function (string $cui) {
    expect(cuiFails($cui))->toBeFalse();
})->with([
    'fără prefix' => '1234565',
    'cu prefix RO' => 'RO1234565',
    'cu prefix și spațiu' => 'RO 1234565',
    'cifră de control zero' => '14399840',
]);

it('respinge CUI-uri invalide', function (string $cui) {
    expect(cuiFails($cui))->toBeTrue();
})->with([
    'cifră de control greșită' => '1234566',
    'prea scurt' => '1',
    'litere' => 'ABC123',
    'gol' => '',
    'prea lung' => '12345678901',
]);
