<?php
declare(strict_types=1);

/*
 * Agenda Personal — MFA encrypted storage.
 *
 * No activa autenticación multifactor por sí mismo.
 * La clave está en el archivo privado montado fuera
 * del DocumentRoot de Apache.
 */

function agendaMfaEncryptionKey(): string
{
    static $key = null;

    if (is_string($key)) {
        return $key;
    }

    $values = parse_ini_file(
        '/run/agenda/agenda.env',
        false,
        INI_SCANNER_RAW
    );

    $hex = is_array($values)
        ? ($values['AGENDA_MFA_KEY_HEX'] ?? null)
        : null;

    if (
        !is_string($hex)
        || preg_match('/\A[0-9a-fA-F]{64}\z/D', $hex) !== 1
    ) {
        throw new RuntimeException(
            'Clave de cifrado MFA no disponible.'
        );
    }

    $decoded = hex2bin($hex);

    if (
        !is_string($decoded)
        || strlen($decoded) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES
    ) {
        throw new RuntimeException(
            'Clave de cifrado MFA inválida.'
        );
    }

    $key = $decoded;
    return $key;
}

function agendaMfaEncryptSecret(string $secret): array
{
    if (
        preg_match('/\A[A-Z2-7]{32}\z/D', $secret) !== 1
    ) {
        throw new InvalidArgumentException(
            'Formato TOTP inválido.'
        );
    }

    $nonce = random_bytes(
        SODIUM_CRYPTO_SECRETBOX_NONCEBYTES
    );

    $cipher = sodium_crypto_secretbox(
        $secret,
        $nonce,
        agendaMfaEncryptionKey()
    );

    return [
        'nonce' => $nonce,
        'cipher' => $cipher,
    ];
}

function agendaMfaDecryptSecret(
    string $nonce,
    string $cipher
): string {
    if (
        strlen($nonce) !== SODIUM_CRYPTO_SECRETBOX_NONCEBYTES
        || strlen($cipher) < SODIUM_CRYPTO_SECRETBOX_MACBYTES
    ) {
        throw new RuntimeException(
            'Datos MFA inválidos.'
        );
    }

    $secret = sodium_crypto_secretbox_open(
        $cipher,
        $nonce,
        agendaMfaEncryptionKey()
    );

    if (
        !is_string($secret)
        || preg_match('/\A[A-Z2-7]{32}\z/D', $secret) !== 1
    ) {
        throw new RuntimeException(
            'No se pudo descifrar el secreto MFA.'
        );
    }

    return $secret;
}

function agendaMfaGenerateRecoveryCodes(): array
{
    $codes = [];

    for ($i = 0; $i < 10; $i++) {
        $raw = strtoupper(
            bin2hex(random_bytes(16))
        );

        $codes[] = implode(
            '-',
            str_split($raw, 4)
        );
    }

    return $codes;
}

function agendaMfaRecoveryHash(string $code): string
{
    $normalized = strtoupper(
        str_replace('-', '', trim($code))
    );

    if (
        preg_match('/\A[0-9A-F]{32}\z/D', $normalized) !== 1
    ) {
        throw new InvalidArgumentException(
            'Código de recuperación inválido.'
        );
    }

    return hash('sha256', $normalized);
}
