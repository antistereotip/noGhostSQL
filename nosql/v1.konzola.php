<?php
die("Silence is golden.");
/*
 * konzola.php - tekstualna konzola za MiniNoSQL (bez GUI-a za podatke).
 *
 * BROWSER (iza prijave):   otvori konzola.php, upisi komande, klikni "Izvrsi"
 * TERMINAL (ako imas):
 *   php konzola.php                      interaktivno (komanda po komanda)
 *   php konzola.php komande.txt          izvrsi fajl sa komandama
 *   php konzola.php 'create ime=Marko'   izvrsi jednu komandu
 */

require __DIR__ . '/v1.komande.php';

$db = new MiniNoSQL(__DIR__ . '/data');
$k = new MiniKonzola($db, __DIR__);

/* ======================================================================
 * TERMINAL
 * ====================================================================== */
if (PHP_SAPI === 'cli') {
    $args = array_slice($argv, 1);

    if ($args) {
        $file = null;

        if (count($args) === 1) {
            foreach ([$args[0], __DIR__ . '/' . $args[0], $k->inputDir() . '/' . $args[0]] as $c) {
                if (is_file($c)) {
                    $file = $c;
                    break;
                }
            }
        }

        $script = $file !== null ? (string) file_get_contents($file) : implode(' ', $args);
        echo $k->run($script) . PHP_EOL;
        exit(0);
    }

    echo "MiniNoSQL konzola. Upisi 'help' za pomoc, 'exit' za izlaz." . PHP_EOL;

    while (true) {
        echo '> ';
        $line = fgets(STDIN);

        if ($line === false) {
            break;
        }

        $line = trim($line);

        if ($line === 'exit' || $line === 'quit') {
            break;
        }

        if ($line !== '') {
            echo $k->line($line) . PHP_EOL;
        }
    }

    exit(0);
}

/* ======================================================================
 * BROWSER
 * ====================================================================== */
require __DIR__ . '/auth.php';
auth_require();

function h($s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$commands = '';
$output = '';

/*
 * Upload slika u ulaz/
 */
function uploadImages(MiniKonzola $k): string
{
    $f = $_FILES['images'] ?? null;

    if (!$f || !is_array($f['name'])) {
        return 'Nista nije izabrano.';
    }

    $types = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_GIF => 'gif', IMAGETYPE_WEBP => 'webp'];
    $lines = [];

    foreach ($f['name'] as $i => $name) {
        $err = $f['error'][$i];

        if ($err === UPLOAD_ERR_NO_FILE) {
            continue;
        }

        if ($err !== UPLOAD_ERR_OK) {
            $lines[] = "Preskoceno: $name (greska pri uploadu, kod $err; mozda je veca od limita servera)";
            continue;
        }

        $tmp = $f['tmp_name'][$i];
        $info = @getimagesize($tmp);

        if ($info === false || !isset($types[$info[2]])) {
            $lines[] = "Preskoceno: $name (nije JPG, PNG, GIF ili WEBP)";
            continue;
        }

        if ($f['size'][$i] > 5 * 1024 * 1024) {
            $lines[] = "Preskoceno: $name (veca od 5 MB)";
            continue;
        }

        $base = preg_replace('/[^A-Za-z0-9_-]/', '_', pathinfo(basename($name), PATHINFO_FILENAME));
        $base = $base !== '' ? $base : 'slika';
        $dest = $k->inputDir() . '/' . $base . '.' . $types[$info[2]];

        if (file_exists($dest)) {
            $dest = $k->inputDir() . '/' . $base . '_' . bin2hex(random_bytes(2)) . '.' . $types[$info[2]];
        }

        if (move_uploaded_file($tmp, $dest)) {
            $lines[] = 'Ubaceno u ulaz/: ' . basename($dest);
        } else {
            $lines[] = "Preskoceno: $name (server nije dozvolio upis u ulaz/, proveri dozvole foldera)";
        }
    }

    return $lines ? implode("\n", $lines) : 'Nista nije izabrano.';
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {

    // upload veci od limita servera: PHP tada odbaci ceo POST
    if (empty($_POST) && empty($_FILES) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
        $output = 'GRESKA: poslato je vise podataka nego sto server dozvoljava (post_max_size / upload_max_filesize). Probaj sa manjom slikom.';

    } elseif (!hash_equals((string) ($_SESSION['csrf'] ?? ''), (string) ($_POST['csrf'] ?? ''))) {
        http_response_code(403);
        exit('Neispravan CSRF token.');

    } else {
        $action = (string) ($_POST['action'] ?? '');

        if ($action === 'run') {
            $commands = (string) ($_POST['commands'] ?? '');
            $output = $k->run($commands);

        } elseif ($action === 'file') {
            $name = (string) ($_POST['file'] ?? '');

            if (preg_match('/^[A-Za-z0-9._-]+\.txt$/', $name) && is_file($k->inputDir() . '/' . $name)) {
                $script = (string) file_get_contents($k->inputDir() . '/' . $name);
                $output = "Fajl: ulaz/$name\n\n" . $k->run($script);
            } else {
                $output = 'GRESKA: fajl ne postoji u ulaz/.';
            }

        } elseif ($action === 'upload') {
            $output = uploadImages($k);
        }
    }
}

// sadrzaj ulaz/
$images = [];
$scripts = [];

foreach (glob($k->inputDir() . '/*') ?: [] as $path) {
    $n = basename($path);

    if (preg_match('/\.(jpg|png|gif|webp)$/i', $n)) {
        $images[] = $n;
    } elseif (preg_match('/^[A-Za-z0-9._-]+\.txt$/', $n)) {
        $scripts[] = $n;
    }
}

$token = (string) $_SESSION['csrf'];

?>
<!DOCTYPE html>
<html lang="sr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>MiniNoSQL konzola</title>
<style>
    :root { --bg:#0f1419; --panel:#161c24; --line:#2a323d; --text:#d7dee8; --muted:#8693a3; --accent:#7ee787; --danger:#ff7b72; }
    * { box-sizing: border-box; }
    body { margin:0; background:var(--bg); color:var(--text); font-family: system-ui, sans-serif; }
    .wrap { max-width: 960px; margin: 0 auto; padding: 20px 16px 40px; }
    h1 { font-size: 20px; margin: 0 0 4px; }
    a { color: var(--accent); }
    .sub { color: var(--muted); margin-bottom: 16px; font-size: 14px; }
    .panel { background: var(--panel); border: 1px solid var(--line); border-radius: 8px; padding: 14px; margin-bottom: 14px; }
    textarea, select, input[type=file] { width:100%; background: #0b0f14; color: var(--text); border:1px solid var(--line); border-radius:6px; padding:10px; font-family: ui-monospace, Menlo, Consolas, monospace; font-size: 14px; }
    textarea { min-height: 170px; resize: vertical; }
    button { background: var(--accent); color:#0b0f14; border:0; border-radius:6px; padding:8px 16px; font: inherit; font-weight: 600; cursor:pointer; margin-top:10px; }
    label { font-size: 13px; color: var(--muted); display:block; margin-bottom:6px; }
    pre { margin:0; white-space: pre-wrap; word-break: break-word; font-family: ui-monospace, Menlo, Consolas, monospace; font-size: 13.5px; line-height: 1.5; }
    .out { background:#0b0f14; border:1px solid var(--line); border-radius:6px; padding:12px; min-height: 60px; }
    .row { display:flex; gap:12px; flex-wrap:wrap; }
    .row > .panel { flex: 1 1 280px; margin-bottom: 0; }
    .files { color: var(--muted); font-size: 13px; margin-top: 10px; font-family: ui-monospace, monospace; }
    details summary { cursor:pointer; color: var(--accent); }
    .nav { float:right; font-size: 14px; }
</style>
</head>
<body>
<div class="wrap">

    <div class="nav"><a href="index.php">index.php</a></div>
    <h1>MiniNoSQL konzola</h1>
    <div class="sub">Komande, jedna po liniji. Linije koje pocinju sa # su komentari.</div>

    <!-- KOMANDE -->
    <div class="panel">
        <form method="post">
            <input type="hidden" name="csrf" value="<?= h($token) ?>">
            <input type="hidden" name="action" value="run">

            <label for="commands">Komande</label>
            <textarea id="commands" name="commands" spellcheck="false"
                placeholder='create ime="Marko" godine=25 @slika=marko.jpg&#10;insert k3x9m2pa email=marko@primer.rs&#10;edit k3x9m2pa godine=26 -email&#10;delete k3x9m2pa&#10;list'><?= h($commands) ?></textarea>

            <button type="submit">Izvrsi</button>
        </form>
    </div>

    <!-- REZULTAT -->
    <div class="panel">
        <label>Rezultat</label>
        <div class="out"><pre><?= $output !== '' ? h($output) : h('(jos nista nije izvrseno)') ?></pre></div>
    </div>

    <div class="row">
        <!-- UPLOAD SLIKA -->
        <div class="panel">
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="csrf" value="<?= h($token) ?>">
                <input type="hidden" name="action" value="upload">

                <label for="images">Ubaci slike u ulaz/ (JPG, PNG, GIF, WEBP, do 5 MB)</label>
                <input id="images" type="file" name="images[]" multiple accept="image/jpeg,image/png,image/gif,image/webp">

                <button type="submit">Ubaci</button>
            </form>

            <div class="files">
                <?php if ($images): ?>
                    Slike u ulaz/: <?= h(implode(', ', $images)) ?>
                <?php else: ?>
                    ulaz/ nema slika.
                <?php endif; ?>
            </div>
        </div>

        <!-- IZVRSI FAJL -->
        <div class="panel">
            <form method="post">
                <input type="hidden" name="csrf" value="<?= h($token) ?>">
                <input type="hidden" name="action" value="file">

                <label for="file">Izvrsi fajl sa komandama iz ulaz/ (.txt)</label>
                <select id="file" name="file" <?= $scripts ? '' : 'disabled' ?>>
                    <?php foreach ($scripts as $s): ?>
                        <option value="<?= h($s) ?>"><?= h($s) ?></option>
                    <?php endforeach; ?>
                    <?php if (!$scripts): ?>
                        <option>(nema .txt fajlova u ulaz/)</option>
                    <?php endif; ?>
                </select>

                <button type="submit" <?= $scripts ? '' : 'disabled' ?>>Izvrsi fajl</button>
            </form>
        </div>
    </div>

    <!-- POMOC -->
    <div class="panel" style="margin-top:14px">
        <details>
            <summary>Pomoc (spisak komandi)</summary>
            <pre style="margin-top:10px"><?= h($k->line('help')) ?></pre>
        </details>
    </div>

</div>
</body>
</html>
