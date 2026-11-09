<?php
declare(strict_types=1);

/*
 * ARK3360-SEC-R33L
 *
 * Persistent login-attempt limiter.
 *
 * Uses the existing registro_rate_limits table.
 * Does not trust client-supplied IP headers.
 *
 * null  = atomically reserve one attempt.
 * false = attempt already counted; no additional write.
 * true  = authentication completed; reset account bucket.
 *
 * The global bucket is never reset by a successful login.
 */

const AGENDA_LOGIN_WINDOW = 900;
const AGENDA_LOGIN_ACCOUNT_MAX = 8;
const AGENDA_LOGIN_GLOBAL_MAX = 120;

function agendaLoginRateLimit(
    string $username,
    ?bool $resultado = null
): bool {
    $username = strtolower(trim($username));

    if ($username === '' || strlen($username) > 100) {
        return false;
    }

    $accountKey = hash(
        'sha256',
        'R33L|LOGIN|ACCOUNT|' . $username
    );

    $globalKey = hash(
        'sha256',
        'R33L|LOGIN|GLOBAL'
    );

    /*
     * A failed password requires no extra increment:
     * the attempt was reserved before password_verify().
     */
    if ($resultado === false) {
        return true;
    }

    try {
        $pdo = db();

        if (
            !$pdo instanceof PDO
            || $pdo->inTransaction()
        ) {
            return false;
        }

        /*
         * Successful authentication clears only the
         * affected account bucket.
         *
         * For ADMIN with MFA, the caller performs this
         * only after successful MFA verification.
         */
        if ($resultado === true) {
            $reset = $pdo->prepare(
                'DELETE FROM registro_rate_limits '
                . 'WHERE bucket_hash = :hash'
            );

            $reset->execute([
                ':hash' => $accountKey
            ]);

            return true;
        }

        $slot = intdiv(
            time(),
            AGENDA_LOGIN_WINDOW
        );

        $buckets = [
            [
                'hash' => $globalKey,
                'limit' => AGENDA_LOGIN_GLOBAL_MAX
            ],
            [
                'hash' => $accountKey,
                'limit' => AGENDA_LOGIN_ACCOUNT_MAX
            ]
        ];

        $firstGlobalAttempt = false;

        /*
         * Global bucket is locked first, followed by
         * account bucket. Consistent ordering serializes
         * concurrent attempts and avoids over-reservation.
         */
        $pdo->beginTransaction();

        try {
            $ensure = $pdo->prepare(
                'INSERT INTO registro_rate_limits '
                . '(bucket_hash, ventana_slot, intentos) '
                . 'VALUES (:hash, :slot, 0) '
                . 'ON DUPLICATE KEY UPDATE '
                . 'bucket_hash = bucket_hash'
            );

            $read = $pdo->prepare(
                'SELECT ventana_slot, intentos '
                . 'FROM registro_rate_limits '
                . 'WHERE bucket_hash = :hash '
                . 'FOR UPDATE'
            );

            $update = $pdo->prepare(
                'UPDATE registro_rate_limits '
                . 'SET ventana_slot = :slot, '
                . 'intentos = :count '
                . 'WHERE bucket_hash = :hash'
            );

            $counts = [];

            foreach ($buckets as $index => $bucket) {
                $key = $bucket['hash'];

                $ensure->execute([
                    ':hash' => $key,
                    ':slot' => $slot
                ]);

                $read->execute([
                    ':hash' => $key
                ]);

                $row = $read->fetch(
                    PDO::FETCH_ASSOC
                );

                $read->closeCursor();

                if (!is_array($row)) {
                    throw new RuntimeException(
                        'Login rate bucket unavailable.'
                    );
                }

                $sameWindow = (
                    (int) $row['ventana_slot']
                    === $slot
                );

                $used = $sameWindow
                    ? (int) $row['intentos']
                    : 0;

                if ($index === 0 && !$sameWindow) {
                    $firstGlobalAttempt = true;
                }

                if ($used >= $bucket['limit']) {
                    $pdo->rollBack();
                    return false;
                }

                $counts[$key] = $used;
            }

            foreach ($buckets as $bucket) {
                $key = $bucket['hash'];

                $update->execute([
                    ':hash' => $key,
                    ':slot' => $slot,
                    ':count' => $counts[$key] + 1
                ]);
            }

            $pdo->commit();

        } catch (Throwable $error) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $error;
        }

        /*
         * Bounded cleanup, outside the critical
         * login transaction.
         *
         * Expired counters older than 24 hours may be
         * removed. Cleanup failures do not invalidate
         * a correctly reserved attempt.
         */
        if ($firstGlobalAttempt) {
            try {
                $cleanup = $pdo->prepare(
                    'DELETE FROM registro_rate_limits '
                    . 'WHERE ventana_slot < :older '
                    . 'LIMIT 500'
                );

                $cleanup->execute([
                    ':older' => max(0, $slot - 96)
                ]);

            } catch (Throwable $ignored) {
                error_log(
                    'R33L login limiter cleanup failed'
                );
            }
        }

        return true;

    } catch (Throwable $error) {
        error_log(
            'R33L login limiter fail-closed: '
            . get_class($error)
        );

        return false;
    }
}
