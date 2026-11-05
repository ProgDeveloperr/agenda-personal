<?php
declare(strict_types=1);

// Componente de vista: las paginas ya validaron la sesion.
$cuentaR8 = contextoTenantWebActual();
$usuarioR8 = htmlspecialchars(
    (string) ($cuentaR8['username'] ?? 'Usuario'),
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
);
$rolR8 = ($cuentaR8['rol'] ?? '') === 'ADMIN' ? 'Administrador' : 'Usuario';
$csrfR8 = htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8');
?>
<details class="r8-account-menu">
    <summary aria-label="Abrir opciones de cuenta" title="Mi cuenta">
        <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="8" r="3.25"/>
            <path d="M5 20c.5-4 3-6 7-6s6.5 2 7 6"/>
        </svg>
        <span class="r8-account-sr">Mi cuenta</span>
    </summary>
    <div class="r8-account-panel">
        <span class="r8-account-eyebrow">SESIÓN ACTIVA</span>
        <strong><?= $usuarioR8 ?></strong>
        <span class="r8-account-role"><?= $rolR8 ?></span>
        <?php if (($cuentaR8['rol'] ?? '') === 'ADMIN'): ?>
        <a class="r9-account-invitations-link" href="/admin-invitaciones.php">
            Administrar invitaciones
        </a>
        <?php endif; ?>
        <form action="/logout.php" method="post">
            <input type="hidden" name="csrf_token" value="<?= $csrfR8 ?>">
            <button type="submit" class="r8-account-logout">
                <svg viewBox="0 0 24 24" width="17" height="17" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M10 4H6a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h4"/><path d="m14 8 4 4-4 4M9 12h9"/></svg>
                Cerrar sesión
            </button>
        </form>
    </div>
</details>
