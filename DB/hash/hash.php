<?php

/*
 * hash.php - podesavanje korisnika i lozinke iz browsera (bez terminala).
 *
 * Upisuje podatke u auth_config.php, tako da ne moras da menjas nijedan fajl rucno.
 * OBAVEZNO OBRISI hash.php sa servera kad zavrsis.
 */

require __DIR__ . '/auth.php';

function h($s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$configFile = __DIR__ . '/auth_config.php';
$cred = auth_credentials();
$configured = $cred['hash'] !== '';

$msg = '';
$msgType = '';
$manualLine = '';
$checkResult = null;

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    /* --- SACUVAJ NOVE PODATKE --- */
    if ($action === 'save') {
        $user = trim((string) ($_POST['user'] ?? ''));
        $pass = (string) ($_POST['pass'] ?? '');
        $current = (string) ($_POST['current'] ?? '');

        if ($user === '' || $pass === '') {
            $msg = 'Unesi i korisnicko ime i lozinku.';
            $msgType = 'bad';

        } elseif ($configured && !password_verify($current, $cred['hash'])) {
            $msg = 'Trenutna lozinka nije tacna, izmena nije dozvoljena.';
            $msgType = 'bad';

        } else {
            $hash = password_hash($pass, PASSWORD_DEFAULT);
            $php = "<?php\nreturn " . var_export(['user' => $user, 'hash' => $hash], true) . ";\n";

            if (@file_put_contents($configFile, $php, LOCK_EX) !== false) {
                if (function_exists('opcache_invalidate')) {
                    @opcache_invalidate($configFile, true);
                }

                $cred = auth_credentials();
                $configured = true;
                $msg = 'Sacuvano. Sada obrisi hash.php i prijavi se na index.php.';
                $msgType = 'ok';

            } else {
                $msg = 'Server ne dozvoljava upis fajla u ovaj folder (proveri dozvole foldera). '
                     . 'Mozes rucno da zalepis ovaj red u auth.php, umesto const AUTH_HASH = \'\';';
                $msgType = 'bad';
                $manualLine = "const AUTH_HASH = '" . $hash . "';";
            }
        }
    }

    /* --- SAMO PROVERA --- */
    if ($action === 'check') {
        $checkResult = [
            'user' => hash_equals($cred['user'], (string) ($_POST['user'] ?? '')),
            'pass' => $cred['hash'] !== '' && password_verify((string) ($_POST['pass'] ?? ''), $cred['hash']),
        ];
    }
}

$hashLen = strlen($cred['hash']);
$hashOk = $hashLen === 60 && strpos($cred['hash'], '$2y$') === 0;

?>
<!DOCTYPE html>
<html lang="sr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Podesavanje prijave</title>
<style>
    body { font-family: system-ui, sans-serif; max-width: 600px; margin: 40px auto; padding: 0 16px; line-height: 1.5; }
    input, textarea { width: 100%; padding: 9px 10px; font: inherit; box-sizing: border-box; margin-bottom: 8px; }
    textarea { font-family: ui-monospace, monospace; font-size: 13px; min-height: 90px; }
    button { padding: 9px 16px; font: inherit; cursor: pointer; }
    .warn { background: #fff3cd; color: #664d03; padding: 10px 14px; border-radius: 6px; margin: 16px 0; }
    .box { border: 1px solid #ccc; border-radius: 6px; padding: 10px 14px 14px; margin: 16px 0; }
    .ok { color: #15803d; } .bad { color: #dc2626; }
    code { background: #8882; padding: 1px 5px; border-radius: 4px; }
    h3 { margin: 4px 0 8px; }
</style>
</head>
<body>
    <h2>Podesavanje prijave</h2>

    <div class="warn">Posle podesavanja obrisi <strong>hash.php</strong> sa servera.</div>

    <?php if ($msg !== ''): ?>
        <p class="<?= h($msgType) ?>"><strong><?= h($msg) ?></strong></p>
        <?php if ($manualLine !== ''): ?>
            <textarea readonly onclick="this.select()"><?= h($manualLine) ?></textarea>
        <?php endif; ?>
    <?php endif; ?>

    <div class="box">
        <strong>Trenutno stanje</strong><br>
        <?php if (!$configured): ?>
            <span class="bad">Lozinka jos nije podesena.</span>
        <?php else: ?>
            Izvor: <code><?= h($cred['source']) ?></code> &middot;
            duzina hash-a: <?= $hashLen ?> &middot;
            <?php if ($hashOk): ?>
                <span class="ok">format ispravan</span>
            <?php else: ?>
                <span class="bad">format NIJE ispravan</span>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <div class="box">
        <h3>1. Postavi korisnika i lozinku</h3>
        <form method="post" autocomplete="off">
            <input type="hidden" name="action" value="save">

            <label for="u1">Korisnicko ime</label>
            <input id="u1" name="user" type="text" value="admin" required>

            <label for="p1">Nova lozinka</label>
            <input id="p1" name="pass" type="text" required>

            <?php if ($configured): ?>
                <label for="c1">Trenutna lozinka (potrebna za promenu)</label>
                <input id="c1" name="current" type="text" required>
            <?php endif; ?>

            <button type="submit">Sacuvaj</button>
        </form>
    </div>

    <?php if ($configured): ?>
    <div class="box">
        <h3>2. Proveri prijavu</h3>
        <form method="post" autocomplete="off">
            <input type="hidden" name="action" value="check">

            <label for="u2">Korisnicko ime</label>
            <input id="u2" name="user" type="text" required>

            <label for="p2">Lozinka</label>
            <input id="p2" name="pass" type="text" required>

            <button type="submit">Proveri</button>
        </form>

        <?php if ($checkResult !== null): ?>
            <p>
                Korisnicko ime:
                <strong class="<?= $checkResult['user'] ? 'ok' : 'bad' ?>"><?= $checkResult['user'] ? 'odgovara' : 'NE odgovara' ?></strong><br>
                Lozinka:
                <strong class="<?= $checkResult['pass'] ? 'ok' : 'bad' ?>"><?= $checkResult['pass'] ? 'odgovara' : 'NE odgovara' ?></strong>
            </p>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</body>
</html>
