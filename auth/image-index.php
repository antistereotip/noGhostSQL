<?php

require __DIR__ . '/auth.php';
auth_require();   // bez prijave se ne ide dalje

require __DIR__ . '/database.php';

$db = new MiniNoSQL(__DIR__ . '/data');

/*
 * POMOCNE FUNKCIJE
 */
function e($s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $url = 'index.php'): void
{
    header('Location: ' . $url);
    exit;
}

function flash(string $type, string $msg): void
{
    $_SESSION['flash'] = ['type' => $type, 'msg' => $msg];
}

/*
 * Obrada slike iz forme (dodavanje, zamena, uklanjanje)
 */
function handleImageUpload(MiniNoSQL $db, string $id): void
{
    if (!empty($_POST['remove_image'])) {
        $db->removeImage($id);
    }

    $f = $_FILES['image'] ?? null;

    if ($f && $f['error'] === UPLOAD_ERR_OK) {
        $db->setImage($id, $f['tmp_name']);
    } elseif ($f && $f['error'] !== UPLOAD_ERR_NO_FILE) {
        throw new Exception('Upload slike nije uspeo (kod ' . $f['error'] . '). Slika je mozda veca od limita servera.');
    }
}

/*
 * Prima JSON ({"ime":"Marko"}) ili linije "kljuc: vrednost".
 * Vraca niz ili null ako je unos neispravan.
 */
function parseInput(string $raw): ?array
{
    $raw = trim($raw);

    if ($raw === '') {
        return null;
    }

    if ($raw[0] === '{' || $raw[0] === '[') {
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : null;
    }

    $data = [];

    foreach (preg_split('/\R/', $raw) as $line) {
        $line = trim($line);

        if ($line === '') {
            continue;
        }

        $pos = strpos($line, ':');

        if ($pos === false) {
            $pos = strpos($line, '=');
        }

        if ($pos === false) {
            return null;
        }

        $key = trim(substr($line, 0, $pos));
        $val = trim(substr($line, $pos + 1));

        if ($key === '') {
            return null;
        }

        if (preg_match('/^-?(0|[1-9]\d*)(\.\d+)?$/', $val)) {
            $val = $val + 0;
        } elseif ($val === 'true') {
            $val = true;
        } elseif ($val === 'false') {
            $val = false;
        } elseif ($val === 'null') {
            $val = null;
        }

        $data[$key] = $val;
    }

    return $data ?: null;
}

/*
 * CSRF ZASTITA
 */
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
}

/*
 * OBRADA AKCIJA (POST)
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $token = $_POST['csrf'] ?? '';

    if (!hash_equals($_SESSION['csrf'], $token)) {
        http_response_code(403);
        exit('Neispravan CSRF token.');
    }

    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'insert') {
            $data = parseInput($_POST['content'] ?? '');

            if ($data === null) {
                flash('err', 'Neispravan unos. Koristi linije "kljuc: vrednost" ili JSON.');
                redirect();
            }

            $id = $db->insert($data);

            try {
                handleImageUpload($db, $id);
                flash('ok', "Dokument dodat. ID: $id");
            } catch (Throwable $ex) {
                flash('err', "Dokument je dodat (ID: $id), ali slika nije sacuvana: " . $ex->getMessage());
            }

        } elseif ($action === 'update') {
            $id = (string) ($_POST['id'] ?? '');
            $data = parseInput($_POST['content'] ?? '');

            if ($data === null) {
                flash('err', 'Neispravan unos.');
                redirect('index.php?edit=' . urlencode($id));
            }

            if ($db->update($id, $data)) {
                try {
                    handleImageUpload($db, $id);
                    flash('ok', "Dokument $id izmenjen.");
                } catch (Throwable $ex) {
                    flash('err', "Dokument $id je izmenjen, ali slika nije sacuvana: " . $ex->getMessage());
                }
            } else {
                flash('err', "Dokument $id nije pronadjen.");
            }

        } elseif ($action === 'delete') {
            $id = (string) ($_POST['id'] ?? '');

            if ($db->delete($id)) {
                flash('ok', "Dokument $id obrisan.");
            } else {
                flash('err', "Dokument $id nije pronadjen.");
            }
        }
    } catch (Throwable $ex) {
        flash('err', 'Greska: ' . $ex->getMessage());
    }

    redirect();
}

/*
 * PRIKAZ (GET)
 */
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$editId = isset($_GET['edit']) ? (string) $_GET['edit'] : '';
$editDoc = $editId !== '' ? $db->find($editId) : null;

$bucket = strtolower((string) ($_GET['bucket'] ?? ''));
$q = strtolower(trim((string) ($_GET['q'] ?? '')));
$color = strtolower((string) ($_GET['color'] ?? ''));

if ($q !== '') {
    $docs = $db->findByPrefix($q);
} elseif ($bucket !== '') {
    $docs = $db->allByBucket($bucket);
} elseif ($color !== '' && in_array($color, $db->colors(), true)) {
    $docs = $db->allByColor($color);
} else {
    $docs = $db->all();
}

usort($docs, fn($a, $b) => strcmp($b['_created'] ?? '', $a['_created'] ?? ''));

$total = $db->count();
$shown = array_slice($docs, 0, 100);

?>
<!DOCTYPE html>
<html lang="sr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>MiniNoSQL</title>
<style>
    :root { --bg:#f5f6f8; --card:#fff; --text:#1d2330; --muted:#6b7280; --line:#e2e5ea; --accent:#2563eb; --danger:#dc2626; --ok:#15803d; }
    @media (prefers-color-scheme: dark) {
        :root { --bg:#12151b; --card:#1b1f27; --text:#e6e9ef; --muted:#9aa3b2; --line:#2c323d; --accent:#60a5fa; --danger:#f87171; --ok:#4ade80; }
    }
    * { box-sizing: border-box; }
    body { margin:0; font-family: system-ui, sans-serif; background:var(--bg); color:var(--text); }
    .wrap { max-width: 960px; margin: 0 auto; padding: 24px 16px; }
    h1 { margin: 0 0 4px; font-size: 24px; }
    .sub { color: var(--muted); margin-bottom: 20px; }
    .card { background: var(--card); border: 1px solid var(--line); border-radius: 10px; padding: 16px; margin-bottom: 16px; }
    textarea, input, select { width:100%; padding:8px 10px; border:1px solid var(--line); border-radius:6px; background:var(--bg); color:var(--text); font: inherit; }
    textarea { min-height: 120px; font-family: ui-monospace, monospace; font-size: 14px; }
    button, .btn { display:inline-block; padding:8px 14px; border:0; border-radius:6px; background:var(--accent); color:#fff; font:inherit; cursor:pointer; text-decoration:none; }
    .btn-sec { background: transparent; color: var(--text); border: 1px solid var(--line); }
    .btn-danger { background: var(--danger); }
    .btn-sm { padding: 4px 10px; font-size: 13px; }
    .row { display:flex; gap:8px; flex-wrap:wrap; align-items:center; }
    .row > * { flex: 1 1 120px; }
    .row > button, .row > .btn { flex: 0 0 auto; }
    .flash { padding: 10px 14px; border-radius: 8px; margin-bottom: 16px; border: 1px solid var(--line); }
    .flash.ok { color: var(--ok); } .flash.err { color: var(--danger); }
    .hint { color: var(--muted); font-size: 13px; margin: 6px 0 10px; }
    .doc { border-top: 1px solid var(--line); padding: 12px 0; }
    .doc:first-child { border-top: 0; }
    .meta { color: var(--muted); font-size: 13px; }
    .id { font-family: ui-monospace, monospace; font-weight: 600; }
    pre { margin: 8px 0; padding: 10px; background: var(--bg); border-radius: 6px; overflow-x: auto; font-size: 13px; }
    form.inline { display:inline; }
    .buckets { display:flex; flex-wrap:wrap; gap:4px; margin-top:10px; }
    .buckets a { padding: 2px 8px; border:2px solid var(--c); border-radius:4px; color:var(--c); text-decoration:none; font-family: ui-monospace, monospace; font-size: 13px; font-weight: 600; }
    .buckets a.on { background: var(--c); color:#fff; }
    .buckets a.empty { opacity: .45; }
    .colors { display:flex; flex-wrap:wrap; gap:8px; margin-top:12px; }
    .colors a { padding: 4px 12px; border-radius: 999px; border:2px solid var(--c); color:var(--c); text-decoration:none; font-size: 13px; font-weight: 600; }
    .colors a.on { background: var(--c); color:#fff; }
    .dot { display:inline-block; width:10px; height:10px; border-radius:50%; background:var(--c); margin-right:6px; }
</style>
</head>
<body>
<div class="wrap">

    <div class="row" style="justify-content:space-between">
        <div style="flex:1 1 auto">
            <h1>MiniNoSQL</h1>
            <div class="sub">Ukupno dokumenata: <strong><?= $total ?></strong></div>
        </div>
        <form method="post" style="flex:0 0 auto">
            <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
            <input type="hidden" name="action" value="logout">
            <button type="submit" class="btn-sec btn-sm" style="border:1px solid var(--line); color:var(--text); background:transparent">Odjavi se</button>
        </form>
    </div>

    <?php if ($flash): ?>
        <div class="flash <?= e($flash['type']) ?>"><?= e($flash['msg']) ?></div>
    <?php endif; ?>

    <!-- FORMA: DODAVANJE / IZMENA -->
    <div class="card">
        <?php if ($editDoc): ?>
            <h3 style="margin-top:0">Izmena dokumenta <span class="id"><?= e($editId) ?></span></h3>
        <?php else: ?>
            <h3 style="margin-top:0">Novi dokument</h3>
            <?php if ($editId !== ''): ?>
                <div class="flash err">Dokument "<?= e($editId) ?>" ne postoji.</div>
            <?php endif; ?>
        <?php endif; ?>

        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
            <input type="hidden" name="action" value="<?= $editDoc ? 'update' : 'insert' ?>">
            <?php if ($editDoc): ?>
                <input type="hidden" name="id" value="<?= e($editId) ?>">
            <?php endif; ?>

            <textarea name="content" placeholder="ime: Marko&#10;godine: 25&#10;grad: Beograd"><?=
                $editDoc ? e(json_encode($editDoc['data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) : ''
            ?></textarea>
            <div class="hint">Jedno polje po liniji (<code>kljuc: vrednost</code>) ili ceo JSON objekat.</div>

            <label class="hint" for="image">Slika (opciono; JPG, PNG, GIF ili WEBP, do 5 MB)</label>
            <input id="image" type="file" name="image" accept="image/jpeg,image/png,image/gif,image/webp" style="margin-bottom:10px">
            <?php if ($editDoc && !empty($editDoc['_image'])): ?>
                <div style="margin-bottom:10px">
                    <img src="image.php?id=<?= e(urlencode($editId)) ?>" alt="" style="max-height:80px; border-radius:6px; vertical-align:middle">
                    <label style="margin-left:8px"><input type="checkbox" name="remove_image" value="1" style="width:auto"> Ukloni sliku</label>
                </div>
            <?php endif; ?>

            <button type="submit"><?= $editDoc ? 'Sacuvaj izmene' : 'Dodaj dokument' ?></button>
            <?php if ($editId !== ''): ?>
                <a class="btn btn-sec" href="index.php">Otkazi</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- PRETRAGA / FILTER PO 36 BUCKET-A -->
    <div class="card">
        <form method="get" class="row">
            <input type="text" name="q" value="<?= e($q) ?>" placeholder="Pretraga po pocetku ID-a (npr. ab3)" maxlength="8">
            <button type="submit">Trazi</button>
            <a class="btn btn-sec" href="index.php">Resetuj</a>
        </form>

        <div class="colors">
            <?php foreach ($db->statsByColor() as $name => $info): ?>
                <a href="?color=<?= e($name) ?>"
                   style="--c: <?= e($name) ?>"
                   class="<?= $color === $name ? 'on' : '' ?>"
                   title="<?= e(implode(' ', $info['chars'])) ?>"><?= e($name) ?> (<?= $info['count'] ?>)</a>
            <?php endforeach; ?>
        </div>

        <div class="buckets">
            <?php foreach ($db->stats() as $char => $n): ?>
                <a href="?bucket=<?= e($char) ?>"
                   style="--c: <?= e($db->colorOf((string) $char)) ?>"
                   class="<?= $bucket === (string) $char ? 'on' : '' ?> <?= $n === 0 ? 'empty' : '' ?>"
                   title="<?= $n ?> dokumenata"><?= e($char) ?> <small><?= $n ?></small></a>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- LISTA DOKUMENATA -->
    <div class="card">
        <h3 style="margin-top:0">
            Dokumenti
            <span class="meta">(prikazano <?= count($shown) ?> od <?= count($docs) ?>)</span>
        </h3>

        <?php if (!$shown): ?>
            <div class="meta">Nema dokumenata.</div>
        <?php endif; ?>

        <?php foreach ($shown as $doc): ?>
            <div class="doc">
                <div class="row" style="justify-content:space-between">
                    <div style="flex:1 1 auto">
                        <span class="id" style="--c: <?= e($db->colorOfId((string) ($doc['_id'] ?? '')) ?? 'gray') ?>"><span class="dot"></span><?= e($doc['_id'] ?? '?') ?></span>
                        <span class="meta">
                            &middot; kreiran <?= e($doc['_created'] ?? '-') ?>
                            <?php if (!empty($doc['_updated'])): ?>
                                &middot; izmenjen <?= e($doc['_updated']) ?>
                            <?php endif; ?>
                        </span>
                    </div>
                    <div style="flex:0 0 auto">
                        <a class="btn btn-sec btn-sm" href="?edit=<?= e(urlencode($doc['_id'] ?? '')) ?>">Izmeni</a>
                        <form method="post" class="inline" onsubmit="return confirm('Obrisati dokument?');">
                            <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= e($doc['_id'] ?? '') ?>">
                            <button type="submit" class="btn-danger btn-sm">Obrisi</button>
                        </form>
                    </div>
                </div>
                <?php if (!empty($doc['_image'])): ?>
                    <img src="image.php?id=<?= e(urlencode($doc['_id'] ?? '')) ?>" alt="" loading="lazy" style="max-height:120px; max-width:100%; border-radius:6px; margin-top:8px">
                <?php endif; ?>
                <pre><?= e(json_encode($doc['data'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?></pre>
            </div>
        <?php endforeach; ?>
    </div>

</div>
</body>
</html>
