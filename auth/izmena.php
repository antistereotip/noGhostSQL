<?php

/*
 * izmena.php - menja dokument kroz kod (bez GUI-a) i prikazuje PRE i POSLE.
 *
 * KORISCENJE (terminal):
 *   php izmena.php <id> polje=vrednost polje2=vrednost -poljeZaBrisanje
 *
 * Primeri:
 *   php izmena.php k3x9m2pa grad="Novi Sad" godine=26
 *   php izmena.php k3x9m2pa -telefon
 *   php izmena.php                      (demo: sam napravi dokument i izmeni ga)
 *
 */

require __DIR__ . '/database.php';

$db = new MiniNoSQL(__DIR__ . '/data');
$isCli = PHP_SAPI === 'cli';

// Menja podatke, pa se pokrece samo iz terminala (ne iz browsera)
if (!$isCli) {
    http_response_code(403);
    exit('Ova skripta radi samo iz terminala.');
}

/*
 * POMOCNE FUNKCIJE
 */
function pretty($value): string
{
    return json_encode(
        $value,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
}

function parseValue(string $val)
{
    if (preg_match('/^-?(0|[1-9]\d*)(\.\d+)?$/', $val)) {
        return $val + 0;
    }

    return match ($val) {
        'true'  => true,
        'false' => false,
        'null'  => null,
        default => $val,
    };
}

function line(string $text = ''): void
{
    echo $text . PHP_EOL;
}

/*
 * ULAZ: id i lista izmena
 */
$args = array_slice($argv, 1);
$id = (string) array_shift($args);
$pairs = $args;

/*
 * DEMO MOD: ako nije dat ID, napravi probni dokument
 */
if ($id === '') {
    $id = $db->insert([
        'ime' => 'Marko',
        'godine' => 25,
        'grad' => 'Beograd',
        'telefon' => '060123456'
    ]);

    $pairs = ['godine=26', 'grad=Novi Sad', '-telefon', 'email=marko@primer.rs'];

    line("DEMO: napravljen probni dokument sa ID-em $id");
    line();
}

/*
 * UCITAJ STANJE PRE IZMENE
 */
$before = $db->find($id);

if ($before === null) {
    line("Greska: dokument '$id' ne postoji.");
    exit(1);
}

if (!$pairs) {
    line('Greska: nisu navedene izmene (npr. grad="Novi Sad" ili -telefon).');
    exit(1);
}

/*
 * PRIMENI IZMENE NA KOPIJU PODATAKA
 */
$newData = $before['data'];

foreach ($pairs as $pair) {
    $pair = (string) $pair;

    // "-polje" znaci: obrisi polje
    if ($pair !== '' && $pair[0] === '-') {
        unset($newData[substr($pair, 1)]);
        continue;
    }

    $pos = strpos($pair, '=');

    if ($pos === false || $pos === 0) {
        line("Preskacem neispravnu izmenu: $pair");
        continue;
    }

    $key = substr($pair, 0, $pos);
    $newData[$key] = parseValue(substr($pair, $pos + 1));
}

/*
 * UPISI I UCITAJ STANJE POSLE IZMENE
 */
if (!$db->update($id, $newData)) {
    line('Greska: izmena nije uspela.');
    exit(1);
}

$after = $db->find($id);

/*
 * ISPIS: PRE
 */
line("=== PRE IZMENE ($id) ===");
line(pretty($before));
line();

/*
 * ISPIS: POSLE
 */
line("=== POSLE IZMENE ($id) ===");
line(pretty($after));
line();

/*
 * ISPIS: RAZLIKE
 */
line('=== PROMENE ===');

$oldData = $before['data'];
$changes = 0;

foreach ($newData as $key => $value) {
    if (!array_key_exists($key, $oldData)) {
        line("  + $key: " . json_encode($value, JSON_UNESCAPED_UNICODE));
        $changes++;
    } elseif ($oldData[$key] !== $value) {
        line(
            "  ~ $key: " .
            json_encode($oldData[$key], JSON_UNESCAPED_UNICODE) .
            ' -> ' .
            json_encode($value, JSON_UNESCAPED_UNICODE)
        );
        $changes++;
    }
}

foreach ($oldData as $key => $value) {
    if (!array_key_exists($key, $newData)) {
        line("  - $key: " . json_encode($value, JSON_UNESCAPED_UNICODE));
        $changes++;
    }
}

if ($changes === 0) {
    line('  (nema promena, vrednosti su iste)');
}
