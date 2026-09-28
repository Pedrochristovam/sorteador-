<?php

declare(strict_types=1);

/**
 * Bootstrap de sessão compartilhado entre páginas e APIs.
 */
function sorteador_boot_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    session_name('SORTEADORSESSID');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => false,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

/**
 * Persiste o fluxo do sorteio em sessão + arquivo (redundância para o mock).
 */
function sorteador_save_fluxo(array $fluxo): void
{
    sorteador_boot_session();
    $_SESSION['sorteio_fluxo'] = $fluxo;

    $dir = dirname(__DIR__) . '/storage';
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    $sid = session_id() ?: 'anon';
    file_put_contents(
        $dir . '/fluxo_' . preg_replace('/[^a-zA-Z0-9_-]/', '', $sid) . '.json',
        json_encode($fluxo, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
    );
}

function sorteador_get_fluxo(): ?array
{
    sorteador_boot_session();
    if (!empty($_SESSION['sorteio_fluxo']) && is_array($_SESSION['sorteio_fluxo'])) {
        return $_SESSION['sorteio_fluxo'];
    }

    $sid = session_id() ?: '';
    if ($sid === '') {
        return null;
    }
    $path = dirname(__DIR__) . '/storage/fluxo_' . preg_replace('/[^a-zA-Z0-9_-]/', '', $sid) . '.json';
    if (!is_file($path)) {
        return null;
    }
    $data = json_decode((string) file_get_contents($path), true);
    return is_array($data) ? $data : null;
}

function sorteador_clear_fluxo(): void
{
    sorteador_boot_session();
    unset($_SESSION['sorteio_fluxo'], $_SESSION['ultimo_resultado_id']);
    $sid = session_id() ?: '';
    if ($sid !== '') {
        $path = dirname(__DIR__) . '/storage/fluxo_' . preg_replace('/[^a-zA-Z0-9_-]/', '', $sid) . '.json';
        if (is_file($path)) {
            unlink($path);
        }
    }
}
