<?php
declare(strict_types=1);

/*
 * Agenda Personal - TOTP RFC 6238.
 * Sin dependencias externas.
 * No almacena ni imprime secretos.
 */

function agendaTotpBase32Encode(string $raw): string
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $bits = '';

    foreach (str_split($raw) as $char) {
        $bits .= str_pad(
            decbin(ord($char)), 8, '0', STR_PAD_LEFT
        );
    }

    $out = '';

    foreach (str_split($bits, 5) as $chunk) {
        $index = bindec(
            str_pad($chunk, 5, '0', STR_PAD_RIGHT)
        );
        $out .= $alphabet[$index];
    }

    return $out;
}

function agendaTotpBase32Decode(string $encoded): string
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $encoded = strtoupper($encoded);

    if (
        $encoded === ''
        || !preg_match('/\A[A-Z2-7]+\z/D', $encoded)
    ) {
        throw new InvalidArgumentException(
            'Clave TOTP invalida'
        );
    }

    $bits = '';

    foreach (str_split($encoded) as $char) {
        $index = strpos($alphabet, $char);

        $bits .= str_pad(
            decbin($index), 5, '0', STR_PAD_LEFT
        );
    }

    $decoded = '';

    for (
        $i = 0, $n = strlen($bits);
        $i + 8 <= $n;
        $i += 8
    ) {
        $decoded .= chr(
            bindec(substr($bits, $i, 8))
        );
    }

    return $decoded;
}

function agendaTotpCode(
    string $secret,
    int $step,
    int $digits = 6
): string {
    if (
        $step < 0
        || !in_array($digits, [6, 8], true)
    ) {
        throw new InvalidArgumentException(
            'Parametro TOTP invalido'
        );
    }

    $key = agendaTotpBase32Decode($secret);

    $counter = pack(
        'N2',
        intdiv($step, 0x100000000),
        $step % 0x100000000
    );

    $hmac = hash_hmac(
        'sha1',
        $counter,
        $key,
        true
    );

    $offset = ord(
        $hmac[strlen($hmac) - 1]
    ) & 15;

    $value = (
        unpack(
            'N',
            substr($hmac, $offset, 4)
        )[1]
    ) & 0x7fffffff;

    return str_pad(
        (string) ($value % (10 ** $digits)),
        $digits,
        '0',
        STR_PAD_LEFT
    );
}

function agendaTotpVerify(
    string $secret,
    string $code,
    int $now,
    int $lastStep = -1
): ?int {
    if (
        !preg_match('/\A[0-9]{6}\z/D', $code)
        || $now < 0
    ) {
        return null;
    }

    $step = intdiv($now, 30);

    for ($delta = -1; $delta <= 1; $delta++) {
        $candidate = $step + $delta;

        if (
            $candidate < 0
            || $candidate <= $lastStep
        ) {
            continue;
        }

        if (hash_equals(
            agendaTotpCode($secret, $candidate),
            $code
        )) {
            return $candidate;
        }
    }

    return null;
}
