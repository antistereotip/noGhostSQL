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

/*
 * Povratak na istu stranu liste posle akcije (samo poznati parametri)
 */
function safeBack(string $qs): string
{
    parse_str($qs, $in);
    $out = [];

    foreach (['q', 'bucket', 'color', 'sort', 'p'] as $k) {
        if (isset($in[$k]) && is_string($in[$k]) && $in[$k] !== '') {
            $out[$k] = $in[$k];
        }
    }

    return 'index.php' . ($out ? '?' . http_build_query($out) : '');
}

/*
 * Tekst iz svih vrednosti dokumenta (za pretragu po sadrzaju)
 */
function docText(array $data): string
{
    $parts = [];

    array_walk_recursive($data, function ($v) use (&$parts) {
        if (is_bool($v)) {
            $parts[] = $v ? 'true' : 'false';
        } elseif ($v !== null) {
            $parts[] = (string) $v;
        }
    });

    return implode(' ', $parts);
}

function containsText(string $haystack, string $needle): bool
{
    return function_exists('mb_stripos')
        ? mb_stripos($haystack, $needle) !== false
        : stripos($haystack, $needle) !== false;
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

        } elseif ($action === 'duplicate') {
            $id = (string) ($_POST['id'] ?? '');
            $src = $db->find($id);

            if ($src === null) {
                flash('err', "Dokument $id nije pronadjen.");
            } else {
                $copyData = is_array($src['data'] ?? null) ? $src['data'] : [];
                $newId = $db->insert($copyData);
                $note = '';
                $img = $db->getImage($id);

                if ($img !== null) {
                    try {
                        $db->setImage($newId, $img['path']);
                    } catch (Throwable $ex) {
                        $note = ' (slika nije kopirana: ' . $ex->getMessage() . ')';
                    }
                }

                flash('ok', "Napravljen duplikat dokumenta $id. Novi ID: $newId. Izmeni ga ispod.$note");
                redirect('index.php?edit=' . urlencode($newId));
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

    redirect(safeBack((string) ($_POST['back'] ?? '')));
}

/*
 * PRIKAZ (GET)
 */
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$editId = isset($_GET['edit']) ? (string) $_GET['edit'] : '';
$editDoc = $editId !== '' ? $db->find($editId) : null;

$bucket = strtolower((string) ($_GET['bucket'] ?? ''));
$q = trim((string) ($_GET['q'] ?? ''));
$color = strtolower((string) ($_GET['color'] ?? ''));
$sort = ($_GET['sort'] ?? 'new') === 'old' ? 'old' : 'new';

if ($bucket !== '' && !(strlen($bucket) === 1 && ctype_alnum($bucket))) {
    $bucket = '';
}

if ($color !== '' && !in_array($color, $db->colors(), true)) {
    $color = '';
}

if ($bucket !== '') {
    $color = '';
    $docs = $db->allByBucket($bucket);
} elseif ($color !== '') {
    $docs = $db->allByColor($color);
} else {
    $docs = $db->all();
}

// pretraga: pocetak ID-a ILI bilo koja vrednost u dokumentu (filteri se kombinuju)
if ($q !== '') {
    $idNeedle = strtolower($q);

    $docs = array_values(array_filter($docs, function ($d) use ($q, $idNeedle) {
        return strpos((string) ($d['_id'] ?? ''), $idNeedle) === 0
            || containsText(docText((array) ($d['data'] ?? [])), $q);
    }));
}

usort($docs, fn($a, $b) => $sort === 'old'
    ? strcmp($a['_created'] ?? '', $b['_created'] ?? '')
    : strcmp($b['_created'] ?? '', $a['_created'] ?? ''));

$total = $db->count();

/*
 * PAGINACIJA (7 dokumenata po strani)
 */
$perPage = 7;
$totalDocs = count($docs);
$pages = max(1, (int) ceil($totalDocs / $perPage));
$page = min(max(1, (int) ($_GET['p'] ?? 1)), $pages);
$shown = array_slice($docs, ($page - 1) * $perPage, $perPage);
$from = $totalDocs ? ($page - 1) * $perPage + 1 : 0;
$to = ($page - 1) * $perPage + count($shown);
$backQs = (string) parse_url(buildUrl(), PHP_URL_QUERY);   // trenutni filteri, za povratak posle akcije

// pravi link sa trenutnim filterima; $over menja pojedine parametre ('' ih uklanja)
function buildUrl(array $over = []): string
{
    global $q, $bucket, $color, $sort, $page;

    $params = array_merge(
        ['q' => $q, 'bucket' => $bucket, 'color' => $color, 'sort' => $sort, 'p' => $page],
        $over
    );

    if (($params['sort'] ?? '') === 'new') {
        unset($params['sort']);
    }

    if ((int) ($params['p'] ?? 1) <= 1) {
        unset($params['p']);
    }

    $params = array_filter($params, fn($v) => $v !== '' && $v !== null);

    return 'index.php' . ($params ? '?' . http_build_query($params) : '');
}

// brojevi strana: 1 ... 4 5 [6] 7 8 ... 20
function pageList(int $page, int $pages): array
{
    $list = [];

    for ($i = 1; $i <= $pages; $i++) {
        if ($i === 1 || $i === $pages || abs($i - $page) <= 2) {
            $list[] = $i;
        } elseif (end($list) !== '...') {
            $list[] = '...';
        }
    }

    return $list;
}

?>
<!DOCTYPE html>
<html lang="sr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>noGhostSQL</title>
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
    .pager { display:flex; flex-wrap:wrap; gap:6px; justify-content:center; margin-top:16px; padding-top:14px; border-top:1px solid var(--line); }
    .pager a, .pager span { padding: 5px 11px; border:1px solid var(--line); border-radius:6px; font-size:14px; text-decoration:none; color:var(--text); }
    .pager a.on { background: var(--accent); border-color: var(--accent); color:#fff; }
    .pager .off { opacity:.4; }
    .pager .gap { border-color: transparent; color: var(--muted); }
</style>
</head>
<body>
<div class="wrap">

    <div class="row" style="justify-content:space-between">
        <div style="flex:1 1 auto">
            <h1>noGhostSQL</h1>
            <div class="sub">Ukupno dokumenata: <strong><?= $total ?></strong>. Ukupno kombinacija indeksa u bazi: <strong>36⁸ = 2,821,109,907,456</strong></div>
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
                <input type="hidden" name="back" value="<?= e($backQs) ?>">
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
                <a class="btn btn-sec" href="<?= e(buildUrl()) ?>">Otkazi</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- PRETRAGA / FILTER PO 36 BUCKET-A -->
    <div class="card">
        <form method="get" class="row">
            <?php if ($bucket !== ''): ?><input type="hidden" name="bucket" value="<?= e($bucket) ?>"><?php endif; ?>
            <?php if ($color !== ''): ?><input type="hidden" name="color" value="<?= e($color) ?>"><?php endif; ?>
            <input type="text" name="q" value="<?= e($q) ?>" placeholder="Pretraga po sadrzaju ili pocetku ID-a (npr. Marko)">
            <select name="sort" onchange="this.form.submit()" title="Redosled" style="flex:0 1 170px">
                <option value="new" <?= $sort === 'new' ? 'selected' : '' ?>>Najnoviji prvo</option>
                <option value="old" <?= $sort === 'old' ? 'selected' : '' ?>>Najstariji prvo</option>
            </select>
            <button type="submit">Trazi</button>
            <a class="btn btn-sec" href="index.php">Resetuj</a>
        </form>

        <div class="colors">
            <?php foreach ($db->statsByColor() as $name => $info): ?>
                <a href="<?= e(buildUrl(['color' => $color === $name ? '' : $name, 'bucket' => '', 'p' => 1])) ?>"
                   style="--c: <?= e($name) ?>"
                   class="<?= $color === $name ? 'on' : '' ?>"
                   title="<?= e(implode(' ', $info['chars'])) ?>"><?= e($name) ?> (<?= $info['count'] ?>)</a>
            <?php endforeach; ?>
        </div>

        <div class="buckets">
            <?php foreach ($db->stats() as $char => $n): ?>
                <a href="<?= e(buildUrl(['bucket' => $bucket === (string) $char ? '' : (string) $char, 'color' => '', 'p' => 1])) ?>"
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
            <span class="meta">(<?= $from ?>-<?= $to ?> od <?= $totalDocs ?>)</span>
        </h3>

        <?php if (!$shown): ?>
            <div class="meta"><?= ($q !== '' || $bucket !== '' || $color !== '') ? 'Nema rezultata za zadate filtere.' : 'Nema dokumenata.' ?></div>
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
                        <button type="button" class="btn-sec btn-sm" data-copy="<?= e($doc['_id'] ?? '') ?>">Kopiraj ID</button>
                        <a class="btn btn-sec btn-sm" href="<?= e(buildUrl(['edit' => (string) ($doc['_id'] ?? '')])) ?>">Izmeni</a>
                        <form method="post" class="inline">
                            <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
                            <input type="hidden" name="action" value="duplicate">
                            <input type="hidden" name="id" value="<?= e($doc['_id'] ?? '') ?>">
                            <button type="submit" class="btn-sec btn-sm">Dupliraj</button>
                        </form>
                        <form method="post" class="inline" onsubmit="return confirm('Obrisati dokument?');">
                            <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= e($doc['_id'] ?? '') ?>">
                            <input type="hidden" name="back" value="<?= e($backQs) ?>">
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

        <?php if ($pages > 1): ?>
            <nav class="pager">
                <?php if ($page > 1): ?>
                    <a href="<?= e(buildUrl(['p' => $page - 1])) ?>">&laquo; Prethodna</a>
                <?php else: ?>
                    <span class="off">&laquo; Prethodna</span>
                <?php endif; ?>

                <?php foreach (pageList($page, $pages) as $n): ?>
                    <?php if ($n === '...'): ?>
                        <span class="gap">&hellip;</span>
                    <?php else: ?>
                        <a href="<?= e(buildUrl(['p' => $n])) ?>" class="<?= $n === $page ? 'on' : '' ?>"><?= $n ?></a>
                    <?php endif; ?>
                <?php endforeach; ?>

                <?php if ($page < $pages): ?>
                    <a href="<?= e(buildUrl(['p' => $page + 1])) ?>">Sledeca &raquo;</a>
                <?php else: ?>
                    <span class="off">Sledeca &raquo;</span>
                <?php endif; ?>
            </nav>
        <?php endif; ?>
    </div>

</div>
<script>
// "Kopiraj ID" dugme
document.addEventListener('click', function (ev) {
    var btn = ev.target.closest('[data-copy]');
    if (!btn) return;

    var text = btn.getAttribute('data-copy');
    var done = function () {
        var old = btn.textContent;
        btn.textContent = 'Kopirano!';
        setTimeout(function () { btn.textContent = old; }, 1500);
    };

    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(text).then(done);
    } else {
        var ta = document.createElement('textarea');
        ta.value = text;
        ta.style.position = 'fixed';
        ta.style.opacity = '0';
        document.body.appendChild(ta);
        ta.select();
        try { document.execCommand('copy'); done(); } catch (e) {}
        document.body.removeChild(ta);
    }
});
</script>
</body>
</html>
