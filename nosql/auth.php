<?php

/*
 * auth.php - prijava korisnickim imenom i lozinkom
 *
 * PODESAVANJE (bez terminala):
 *   Otvori hash.php u browseru i postavi korisnicko ime i lozinku.
 *   Podaci se upisuju u auth_config.php, pa ne moras da menjas ovaj fajl.
 *   (Konstante ispod su samo rezerva ako auth_config.php ne postoji.)
 */

const AUTH_USER = 'admin';
const AUTH_HASH = '';                 // <-- ovde ide hash (php auth.php lozinka)
const AUTH_SESSION_TTL = 8 * 3600;    // sesija traje 8 sati
const AUTH_MAX_FAILS = 5;             // max pogresnih pokusaja
const AUTH_FAIL_WINDOW = 15 * 60;     // u okviru 15 minuta, po IP adresi

/*
 * GENERATOR HASH-a (samo iz terminala: php auth.php lozinka)
 */
if (PHP_SAPI === 'cli' && isset($argv[0]) && realpath($argv[0]) === __FILE__) {
    if (empty($argv[1])) {
        echo "Koriscenje: php auth.php tvoja_lozinka" . PHP_EOL;
        exit(1);
    }

    echo "Kopiraj ovaj red u auth.php:" . PHP_EOL . PHP_EOL;
    echo "const AUTH_HASH = '" . password_hash($argv[1], PASSWORD_DEFAULT) . "';" . PHP_EOL;
    exit(0);
}

/*
 * PODACI ZA PRIJAVU
 * Ako postoji auth_config.php (pravi ga hash.php), koristi se on.
 * Inace se koriste konstante AUTH_USER i AUTH_HASH odozgo.
 */
function auth_credentials(): array
{
    $file = __DIR__ . '/auth_config.php';

    if (is_file($file)) {
        $cfg = include $file;

        if (is_array($cfg) && !empty($cfg['hash'])) {
            return [
                'user' => (string) ($cfg['user'] ?? AUTH_USER),
                'hash' => (string) $cfg['hash'],
                'source' => 'auth_config.php',
            ];
        }
    }

    return ['user' => AUTH_USER, 'hash' => AUTH_HASH, 'source' => 'auth.php'];
}

/*
 * SESIJA
 */
function auth_start(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);

    session_start();
}

/*
 * ZASTITA OD POGADJANJA LOZINKE (po IP adresi)
 */
function auth_throttle_file(): string
{
    return sys_get_temp_dir() . '/mininosql_login_' . md5($_SERVER['REMOTE_ADDR'] ?? 'x') . '.json';
}

function auth_recent_fails(): array
{
    $file = auth_throttle_file();

    if (!is_file($file)) {
        return [];
    }

    $list = json_decode((string) @file_get_contents($file), true);

    if (!is_array($list)) {
        return [];
    }

    return array_values(array_filter(
        $list,
        fn($t) => is_int($t) && $t > time() - AUTH_FAIL_WINDOW
    ));
}

function auth_register_fail(): void
{
    $list = auth_recent_fails();
    $list[] = time();
    @file_put_contents(auth_throttle_file(), json_encode($list), LOCK_EX);
}

function auth_clear_fails(): void
{
    @unlink(auth_throttle_file());
}

/*
 * GLAVNA FUNKCIJA: pozovi na vrhu stranice koju stitis
 */
function auth_require(): void
{
    auth_start();

    $cred = auth_credentials();

    if ($cred['hash'] === '') {
        http_response_code(500);
        exit('Lozinka nije podesena. Otvori hash.php u browseru i postavi lozinku.');
    }

    $isPost = ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';
    $logged = !empty($_SESSION['auth_ok'])
        && (time() - (int) ($_SESSION['auth_time'] ?? 0)) < AUTH_SESSION_TTL;

    /* --- vec prijavljen --- */
    if ($logged) {

        if ($isPost && ($_POST['action'] ?? '') === 'logout') {
            if (hash_equals((string) ($_SESSION['csrf'] ?? ''), (string) ($_POST['csrf'] ?? ''))) {
                $_SESSION = [];
                session_destroy();
                header('Location: ' . $_SERVER['SCRIPT_NAME']);
                exit;
            }
        }

        return;
    }

    /* --- nije prijavljen --- */
    unset($_SESSION['auth_ok'], $_SESSION['auth_time']);

    $error = '';

    if ($isPost && ($_POST['action'] ?? '') === 'login') {

        if (!hash_equals((string) ($_SESSION['login_csrf'] ?? ''), (string) ($_POST['login_csrf'] ?? ''))) {
            $error = 'Sesija je istekla, pokusaj ponovo.';

        } elseif (count(auth_recent_fails()) >= AUTH_MAX_FAILS) {
            $error = 'Previse pogresnih pokusaja. Pokusaj ponovo kasnije.';

        } else {
            // proveravaju se oba polja, da se ne otkrije koje je tacno
            $userOk = hash_equals($cred['user'], (string) ($_POST['user'] ?? ''));
            $passOk = password_verify((string) ($_POST['pass'] ?? ''), $cred['hash']);

            if ($userOk && $passOk) {
                auth_clear_fails();
                session_regenerate_id(true);
                $_SESSION['auth_ok'] = true;
                $_SESSION['auth_time'] = time();
                $_SESSION['csrf'] = bin2hex(random_bytes(16));
                header('Location: ' . $_SERVER['SCRIPT_NAME']);
                exit;
            }

            auth_register_fail();
            sleep(1);
            $error = 'Pogresno korisnicko ime ili lozinka.';
        }
    }

    $_SESSION['login_csrf'] = bin2hex(random_bytes(16));

    http_response_code(401);
    auth_render_login($error, $_SESSION['login_csrf']);
    exit;
}

/*
 * STRANICA ZA PRIJAVU
 */
function auth_render_login(string $error, string $token): void
{
    $e = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    ?>
<!DOCTYPE html>
<html lang="sr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Prijava</title>
<style>
    :root { --bg:#f5f6f8; --card:#fff; --text:#1d2330; --muted:#6b7280; --line:#e2e5ea; --accent:#2563eb; --danger:#dc2626; }
    @media (prefers-color-scheme: dark) {
        :root { --bg:#12151b; --card:#1b1f27; --text:#e6e9ef; --muted:#9aa3b2; --line:#2c323d; --accent:#60a5fa; --danger:#f87171; }
    }
    * { box-sizing: border-box; }
    body { margin:0; min-height:100vh; display:flex; align-items:center; justify-content:center; font-family: system-ui, sans-serif; background:var(--bg); color:var(--text); padding:16px; }
    .box { width:100%; max-width:340px; background:var(--card); border:1px solid var(--line); border-radius:10px; padding:24px; }
    h1 { margin:0 0 4px; font-size:22px; }
    .sub { color:var(--muted); font-size:14px; margin-bottom:18px; }
    label { display:block; font-size:13px; color:var(--muted); margin:12px 0 4px; }
    input { width:100%; padding:9px 10px; border:1px solid var(--line); border-radius:6px; background:var(--bg); color:var(--text); font:inherit; }
    button { width:100%; margin-top:18px; padding:10px; border:0; border-radius:6px; background:var(--accent); color:#fff; font:inherit; cursor:pointer; }
    .err { color:var(--danger); font-size:14px; margin-bottom:8px; }
</style>
</head>
<body>
    <form class="box" method="post" autocomplete="on">
        <h1>noGhostSQL</h1>
        <div class="sub">Prijavi se za nastavak</div>

        <?php if ($error !== ''): ?>
            <div class="err"><?= $e($error) ?></div>
        <?php endif; ?>

        <input type="hidden" name="action" value="login">
        <input type="hidden" name="login_csrf" value="<?= $e($token) ?>">

        <label for="user">Korisnicko ime</label>
        <input id="user" name="user" type="text" autocomplete="username" required autofocus>

        <label for="pass">Lozinka</label>
        <input id="pass" name="pass" type="password" autocomplete="current-password" required>

        <button type="submit">Prijavi se</button>
    </form>
</body>
</html>
    <?php
}
