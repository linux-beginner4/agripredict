<?php
require_once __DIR__ . '/../config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/** Échappement HTML */
function e(?string $s): string {
    return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8');
}

function redirect(string $url): never {
    header('Location: ' . $url);
    exit;
}

/** Jeton CSRF */
function csrf_token(): string {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string {
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function csrf_check(): void {
    $ok = isset($_POST['csrf'], $_SESSION['csrf'])
        && hash_equals($_SESSION['csrf'], $_POST['csrf']);
    if (!$ok) {
        http_response_code(403);
        exit('Requête invalide (CSRF). Rechargez la page.');
    }
}

/** Utilisateur connecté ? */
function est_connecte(): bool {
    return !empty($_SESSION['user_id']);
}

function exiger_connexion(): void {
    if (!est_connecte()) {
        redirect('login.php');
    }
}

function utilisateur_courant(): ?array {
    if (!est_connecte()) {
        return null;
    }
    $st = db()->prepare('SELECT id, nom, email FROM utilisateurs WHERE id = ?');
    $st->execute([$_SESSION['user_id']]);
    return $st->fetch() ?: null;
}

/** Messages flash */
function flash(string $type, string $msg): void {
    $_SESSION['flash'][] = ['type' => $type, 'msg' => $msg];
}

function flash_afficher(): void {
    foreach ($_SESSION['flash'] ?? [] as $f) {
        echo '<div class="alert alert-' . e($f['type']) . '">' . e($f['msg']) . '</div>';
    }
    unset($_SESSION['flash']);
}

/** Format FCFA */
function fcfa(int|float $n): string {
    return number_format($n, 0, ',', ' ') . ' FCFA';
}
