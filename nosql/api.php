<?php

/*
 * api.php - javni API SAMO ZA CITANJE za stranicu pregled.php.
 * Ne trazi prijavu, pa pazi sta stavljas u bazu: sve sto je u dokumentu je vidljivo.
 *
 *   api.php?action=list&page=1&per=12&q=marko&color=seagreen&bucket=k&sort=new&stats=1
 *   api.php?action=get&id=k3x9m2pa
 *   api.php?action=image&id=k3x9m2pa
 *
 * Sta se NE prikazuje javno:
 *   - dokument koji ima polje  privatno: true
 *   - polja cije ime pocinje sa _  (npr. _beleska)
 *   - polja navedena u HIDDEN_FIELDS ispod (u svim nivoima dokumenta)
 */

const HIDDEN_FIELDS = [];          // npr. ['telefon', 'email', 'lozinka']
const PRIVATE_FLAG  = 'privatno';  // privatno: true  =>  dokument se ne prikazuje
const MAX_PER_PAGE  = 48;
const DEBUG         = false;       // true = u odgovoru se vidi tekst greske (samo dok trazis kvar)

// upozorenja se ne ispisuju u odgovor da ne pokvare JSON
ini_set('display_errors', '0');

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

// svaka greska se vraca kao JSON, a ne kao prazna strana
set_exception_handler(function ($e) {
    http_response_code(500);
    echo json_encode(
        ['error' => DEBUG ? get_class($e) . ': ' . $e->getMessage() : 'Greska na serveru. Postavi DEBUG na true u api.php da vidis uzrok.'],
        JSON_UNESCAPED_UNICODE
    );
});

register_shutdown_function(function () {
    $err = error_get_last();

    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true) && !headers_sent()) {
        http_response_code(500);
        echo json_encode(
            ['error' => DEBUG ? $err['message'] : 'Greska na serveru. Postavi DEBUG na true u api.php da vidis uzrok.'],
            JSON_UNESCAPED_UNICODE
        );
    }
});

if (!is_file(__DIR__ . '/database.php')) {
    http_response_code(500);
    exit(json_encode(['error' => 'Fajl database.php nije u istom folderu kao api.php.'], JSON_UNESCAPED_UNICODE));
}

require_once __DIR__ . '/database.php';

// api.php koristi boje i slike, pa trazi noviju verziju database.php
foreach (['colors', 'colorOfId', 'bucketsByColor', 'getImage', 'buckets', 'find', 'all'] as $m) {
    if (!class_exists('MiniNoSQL') || !method_exists('MiniNoSQL', $m)) {
        http_response_code(500);
        exit(json_encode(
            ['error' => 'database.php je stara verzija (nedostaje metod ' . $m . '). Ubaci najnoviji database.php na server.'],
            JSON_UNESCAPED_UNICODE
        ));
    }
}

if (!in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET', 'HEAD'], true)) {
    http_response_code(405);
    header('Allow: GET, HEAD');
    echo json_encode(['error' => 'Dozvoljeno je samo citanje (GET).']);
    exit;
}

function respond($data, int $code = 200): void
{
    http_response_code($code);

    if ($code === 200) {
        header('Cache-Control: public, max-age=15');
    }

    echo json_encode(
        $data,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
    );
    exit;
}

/* ------------------------------------------------------------------
 * YOUTUBE (isto pravilo kao u index.php)
 * ------------------------------------------------------------------ */
function youtubeParse(string $url): ?array
{
    $url = trim($url);

    if (preg_match('#^(www\.|m\.)?(youtube\.com|youtu\.be)/#i', $url)) {
        $url = 'https://' . $url;
    }

    if (!preg_match('#^https?://#i', $url)) {
        return null;
    }

    $p = parse_url($url);

    if (!$p || empty($p['host'])) {
        return null;
    }

    $host = strtolower((string) preg_replace('/^(www\.|m\.|music\.)/i', '', $p['host']));
    $path = $p['path'] ?? '';
    parse_str($p['query'] ?? '', $qs);
    $id = null;

    if ($host === 'youtu.be') {
        $id = explode('/', ltrim($path, '/'))[0];
    } elseif ($host === 'youtube.com' || $host === 'youtube-nocookie.com') {
        if ($path === '/watch') {
            $id = $qs['v'] ?? null;
        } elseif (preg_match('#^/(embed|shorts|live|v)/([^/?]+)#', $path, $m)) {
            $id = $m[2];
        }
    } else {
        return null;
    }

    if (!is_string($id) || !preg_match('/^[A-Za-z0-9_-]{11}$/', $id)) {
        return null;
    }

    $start = 0;
    $t = $qs['t'] ?? ($qs['start'] ?? '');

    if (is_string($t) && $t !== '') {
        if (ctype_digit($t)) {
            $start = (int) $t;
        } elseif (preg_match('/^(?:(\d+)h)?(?:(\d+)m)?(?:(\d+)s)?$/', $t, $m)) {
            $start = (int) ($m[1] ?? 0) * 3600 + (int) ($m[2] ?? 0) * 60 + (int) ($m[3] ?? 0);
        }
    }

    return ['id' => $id, 'start' => $start];
}

function findVideos(array $data, string $prefix = ''): array
{
    $out = [];

    foreach ($data as $k => $v) {
        $path = $prefix === '' ? (string) $k : $prefix . '.' . $k;

        if (is_array($v)) {
            $out = array_merge($out, findVideos($v, $path));
        } elseif (is_string($v) && ($yt = youtubeParse($v)) !== null) {
            $out[] = ['path' => $path] + $yt;
        }
    }

    return array_slice($out, 0, 5);
}

/* ------------------------------------------------------------------
 * PRIVATNOST
 * ------------------------------------------------------------------ */
function isPrivate(array $data): bool
{
    $v = $data[PRIVATE_FLAG] ?? false;

    return $v === true || $v === 1
        || (is_string($v) && in_array(strtolower($v), ['true', 'da', '1'], true));
}

function cleanData($data)
{
    if (!is_array($data)) {
        return $data;
    }

    $hidden = array_map('strtolower', HIDDEN_FIELDS);
    $out = [];

    foreach ($data as $k => $v) {
        if (is_string($k) && ($k === '' || $k[0] === '_' || in_array(strtolower($k), $hidden, true))) {
            continue;
        }

        $out[$k] = cleanData($v);
    }

    return $out;
}

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

/*
 * Svi dokumenti koji smeju da budu javni, vec ociscenih podataka.
 */
function publicDocs(MiniNoSQL $db): array
{
    $items = [];

    foreach ($db->all() as $doc) {
        $id = (string) ($doc['_id'] ?? '');

        if (!preg_match('/^[a-z0-9]{8}$/', $id)) {
            continue;
        }

        $data = is_array($doc['data'] ?? null) ? $doc['data'] : [];

        if (isPrivate($data)) {
            continue;
        }

        $items[] = [
            'id'      => $id,
            'color'   => $db->colorOfId($id),
            'created' => (string) ($doc['_created'] ?? ''),
            'image'   => !empty($doc['_image']),
            'data'    => cleanData($data),
        ];
    }

    return $items;
}

function withVideos(array $item): array
{
    $item['videos'] = findVideos((array) $item['data']);

    return $item;
}

/* ------------------------------------------------------------------
 * AKCIJE
 * ------------------------------------------------------------------ */
$db = new MiniNoSQL(__DIR__ . '/data');
$action = (string) ($_GET['action'] ?? 'list');

/* --- jedan dokument --- */
if ($action === 'get') {
    $id = strtolower((string) ($_GET['id'] ?? ''));
    $doc = preg_match('/^[a-z0-9]{8}$/', $id) ? $db->find($id) : null;

    if ($doc === null || isPrivate((array) ($doc['data'] ?? []))) {
        respond(['error' => 'Dokument ne postoji.'], 404);
    }

    respond(withVideos([
        'id'      => $id,
        'color'   => $db->colorOfId($id),
        'created' => (string) ($doc['_created'] ?? ''),
        'image'   => !empty($doc['_image']),
        'data'    => cleanData((array) ($doc['data'] ?? [])),
    ]));
}

/* --- slika dokumenta --- */
if ($action === 'image') {
    $id = strtolower((string) ($_GET['id'] ?? ''));
    $doc = preg_match('/^[a-z0-9]{8}$/', $id) ? $db->find($id) : null;
    $image = $doc !== null && !isPrivate((array) ($doc['data'] ?? [])) ? $db->getImage($id) : null;

    if ($image === null) {
        respond(['error' => 'Slika ne postoji.'], 404);
    }

    header('Content-Type: ' . $image['mime']);
    header('Content-Length: ' . filesize($image['path']));
    header('Cache-Control: public, max-age=86400');
    readfile($image['path']);
    exit;
}

/* --- lista, pretraga, statistika --- */
if ($action === 'list') {
    $q      = trim((string) ($_GET['q'] ?? ''));
    $color  = strtolower((string) ($_GET['color'] ?? ''));
    $bucket = strtolower((string) ($_GET['bucket'] ?? ''));
    $sort   = (string) ($_GET['sort'] ?? 'new');
    $seed   = substr(preg_replace('/[^a-z0-9]/i', '', (string) ($_GET['seed'] ?? '')), 0, 16);
    $page   = max(1, (int) ($_GET['page'] ?? 1));
    $per    = min(MAX_PER_PAGE, max(1, (int) ($_GET['per'] ?? 12)));

    if (!in_array($color, $db->colors(), true)) {
        $color = '';
    }

    if (!preg_match('/^[a-z0-9]$/', $bucket)) {
        $bucket = '';
    }

    $all = publicDocs($db);
    $result = ['ok' => true];

    // statistika po svih 36 karaktera i 4 boje (uvek nad svim javnim dokumentima)
    if (($_GET['stats'] ?? '') === '1') {
        $buckets = array_fill_keys($db->buckets(), 0);

        foreach ($all as $it) {
            $buckets[$it['id'][0]]++;
        }

        $colors = [];

        foreach ($db->colors() as $name) {
            $chars = $db->bucketsByColor($name);
            $colors[] = [
                'name'  => $name,
                'chars' => $chars,
                'count' => array_sum(array_map(fn($c) => $buckets[$c], $chars)),
            ];
        }

        $result['stats'] = ['total' => count($all), 'buckets' => $buckets, 'colors' => $colors];
    }

    // filteri
    $items = array_values(array_filter($all, function ($it) use ($q, $color, $bucket) {
        if ($bucket !== '' && $it['id'][0] !== $bucket) {
            return false;
        }

        if ($color !== '' && $it['color'] !== $color) {
            return false;
        }

        if ($q !== '') {
            return strpos($it['id'], strtolower($q)) === 0
                || containsText(docText((array) $it['data']), $q);
        }

        return true;
    }));

    // redosled
    if ($sort === 'random') {
        $keys = [];

        foreach ($items as $it) {
            $keys[$it['id']] = md5($seed . $it['id']);
        }

        usort($items, fn($a, $b) => strcmp($keys[$a['id']], $keys[$b['id']]));
    } else {
        usort($items, fn($a, $b) => $sort === 'old'
            ? strcmp($a['created'], $b['created'])
            : strcmp($b['created'], $a['created']));
    }

    $found = count($items);
    $pages = max(1, (int) ceil($found / $per));
    $page  = min($page, $pages);

    $result += [
        'found' => $found,
        'page'  => $page,
        'pages' => $pages,
        'per'   => $per,
        'items' => array_map('withVideos', array_slice($items, ($page - 1) * $per, $per)),
    ];

    respond($result);
}

respond(['error' => 'Nepoznata akcija.'], 400);
