<?php
//die("Silence is golden.");
/*
 * komande.php - jezgro tekstualne konzole za MiniNoSQL.
 * Pokrece se preko konzola.php (browser ili terminal).
 *
 * KOMANDE (jedna po liniji; mogu i sa zagradama: create(ime="Marko")):
 *   create  [polja] [@slika=putanja]       novi dokument
 *   insert  ID polja [@slika=putanja]      dodaje NOVA polja u postojeci dokument
 *   edit    ID polja [-polje] [@slika=..]  menja POSTOJECA polja (ili brise: -polje)
 *   delete  ID [polje ...]                 brise dokument (ili samo navedena polja)
 *   show    ID                             prikazuje dokument
 *   list    [boja|karakter|prefiks]        lista dokumenata
 *   count                                  broj dokumenata
 *   help                                   pomoc
 */

// Ovo je biblioteka, ne stranica: ako je neko otvori direktno, prebaci na konzola.php

if (PHP_SAPI !== 'cli'
    && isset($_SERVER['SCRIPT_FILENAME'])
    && realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    header('Location: konzola.php');
    exit('Otvori konzola.php');
}

require_once __DIR__ . '/database.php';

class MiniKonzola
{
    private MiniNoSQL $db;
    private string $baseDir;
    private string $inputDir;

    public function __construct(MiniNoSQL $db, ?string $baseDir = null)
    {
        $this->db = $db;
        $this->baseDir = $baseDir ?? __DIR__;
        $this->inputDir = $this->baseDir . '/ulaz';

        // ulaz/ je folder u koji stavljas slike (i .txt fajlove sa komandama)
        if (!is_dir($this->inputDir)) {
            @mkdir($this->inputDir, 0777, true);
        }

        $ht = $this->inputDir . '/.htaccess';

        if (is_dir($this->inputDir) && !file_exists($ht)) {
            @file_put_contents(
                $ht,
                "<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n" .
                "<IfModule !mod_authz_core.c>\n    Deny from all\n</IfModule>\n"
            );
        }
    }

    public function inputDir(): string
    {
        return $this->inputDir;
    }

    /* ------------------------------------------------------------------
     * IZVRSAVANJE
     * ------------------------------------------------------------------ */

    /*
     * Izvrsava vise komandi (jedna po liniji). Prazne linije i linije
     * koje pocinju sa # se preskacu.
     */
    public function run(string $script): string
    {
        $out = [];
        $count = 0;
        $errors = 0;

        foreach (preg_split('/\R/', $script) as $line) {
            $line = trim($line);

            if ($line === '' || $line[0] === '#') {
                continue;
            }

            $count++;
            $out[] = '> ' . $line;

            $result = $this->line($line);

            if (strpos($result, 'GRESKA:') === 0) {
                $errors++;
            }

            $out[] = $result;
            $out[] = '';
        }

        $out[] = "Izvrseno komandi: $count, gresaka: $errors";

        return implode("\n", $out);
    }

    /*
     * Izvrsava jednu komandu i vraca tekst rezultata (greska ne prekida rad).
     */
    public function line(string $line): string
    {
        try {
            return implode("\n", $this->exec(trim($line)));
        } catch (Throwable $e) {
            return 'GRESKA: ' . $e->getMessage();
        }
    }

    private function exec(string $line): array
    {
        $line = trim((string) preg_replace('/;\s*$/', '', $line));

        if (preg_match('/^(\w+)\s*\((.*)\)$/s', $line, $m)) {
            $cmd = strtolower($m[1]);
            $args = trim($m[2]);
        } elseif (preg_match('/^(\w+)(?:\s+(.*))?$/s', $line, $m)) {
            $cmd = strtolower($m[1]);
            $args = trim($m[2] ?? '');
        } else {
            throw new Exception('Neispravna komanda. Upisi help.');
        }

        switch ($cmd) {
            case 'help':   return $this->cmdHelp();
            case 'create': return $this->cmdCreate($args);
            case 'insert': return $this->cmdInsert($args);
            case 'edit':   return $this->cmdEdit($args);
            case 'delete': return $this->cmdDelete($args);
            case 'show':   return $this->cmdShow($args);
            case 'list':   return $this->cmdList($args);
            case 'count':  return $this->cmdCount();
        }

        throw new Exception("Nepoznata komanda '$cmd'. Upisi help.");
    }

    /* ------------------------------------------------------------------
     * KOMANDE
     * ------------------------------------------------------------------ */

    private function cmdHelp(): array
    {
        return [
            'KOMANDE (jedna po liniji; # je komentar; moze i sa zagradama, npr. create(ime="Marko"))',
            '',
            '  create  [polja] [@slika=putanja]        novi dokument',
            '  insert  ID polja [@slika=putanja]       dodaje NOVA polja (greska ako polje vec postoji)',
            '  edit    ID polja [-polje] [@slika=..]   menja POSTOJECA polja (greska ako ne postoje)',
            '  delete  ID                              brise ceo dokument (i njegovu sliku)',
            '  delete  ID polje1 polje2                brise samo navedena polja',
            '  show    ID                              prikazuje dokument',
            '  list    [boja|karakter|prefiks]         lista (najvise 50, najnoviji prvo)',
            '  count                                   broj dokumenata po bojama',
            '',
            'POLJA',
            '  ime=Marko   godine=25   grad="Novi Sad"   aktivan=true',
            '  adresa.grad=Beograd           (tacka pravi ugnezdeno polje)',
            '  -telefon                      (brise polje, samo u edit)',
            '  Citav JSON: create {"ime":"Ana","tagovi":["a","b"]}',
            '  Vrednosti sa razmakom ili zarezom stavi pod navodnike.',
            '  Brojevi, true, false i null se prepoznaju sami; "25" pod navodnicima ostaje tekst.',
            '',
            'SLIKE',
            '  Slike prvo ubaci u folder ulaz/ (upload na stranici ili FTP).',
            '  @slika=marko.jpg      dodaje ili zamenjuje sliku',
            '  @slika=-              uklanja sliku',
            '',
            'PRIMERI',
            '  create ime="Marko" godine=25 @slika=marko.jpg',
            '  insert k3x9m2pa email=marko@primer.rs adresa.grad="Novi Sad"',
            '  edit k3x9m2pa godine=26 -email @slika=nova.png',
            '  delete k3x9m2pa telefon',
            '  delete k3x9m2pa',
        ];
    }

    private function cmdCreate(string $args): array
    {
        $ops = $this->parseOps($args);

        if ($ops['remove']) {
            throw new Exception('U create nije moguce brisanje polja (-polje).');
        }

        $img = $this->checkImage($ops['image']);

        if ($img === '-') {
            throw new Exception('Novi dokument nema sliku koju bi trebalo ukloniti.');
        }

        $data = [];

        foreach ($ops['set'] as [$keys, $value]) {
            $this->setPath($data, $keys, $value);
        }

        $id = $this->db->insert($data);

        $out = [
            "Kreiran dokument $id (boja: " . $this->db->colorOfId($id) . ')',
            'PODACI: ' . $this->j($data),
        ];

        return array_merge($out, $this->imageLines($id, $img));
    }

    private function cmdInsert(string $args): array
    {
        [$id, $rest] = $this->splitId($args);
        $ops = $this->parseOps($rest);

        if ($ops['remove']) {
            throw new Exception('U insert nije moguce brisanje polja. Koristi edit ili delete.');
        }

        if (!$ops['set'] && $ops['image'] === null) {
            throw new Exception('Nema sta da se ubaci. Navedi polja (polje=vrednost) ili @slika=putanja.');
        }

        $img = $this->checkImage($ops['image']);
        $doc = $this->mustFind($id);

        $old = $doc['data'];
        $new = $old;
        $exists = [];

        foreach ($ops['set'] as [$keys, $value]) {
            if ($this->pathExists($new, $keys)) {
                $exists[] = implode('.', $keys);
            } else {
                $this->setPath($new, $keys, $value);
            }
        }

        if ($exists) {
            throw new Exception(
                'Polje vec postoji: ' . implode(', ', $exists) . ' (za izmenu koristi edit). Nista nije izmenjeno.'
            );
        }

        if ($ops['set']) {
            $this->db->update($id, $new);
        }

        return $this->report($id, $old, $new, $img);
    }

    private function cmdEdit(string $args): array
    {
        [$id, $rest] = $this->splitId($args);
        $ops = $this->parseOps($rest);

        if (!$ops['set'] && !$ops['remove'] && $ops['image'] === null) {
            throw new Exception('Nema sta da se menja. Navedi polje=vrednost, -polje ili @slika=putanja.');
        }

        $img = $this->checkImage($ops['image']);
        $doc = $this->mustFind($id);

        $old = $doc['data'];
        $new = $old;
        $missing = [];

        foreach ($ops['set'] as [$keys, $value]) {
            if (!$this->pathExists($new, $keys)) {
                $missing[] = implode('.', $keys);
            } else {
                $this->setPath($new, $keys, $value);
            }
        }

        foreach ($ops['remove'] as $keys) {
            if (!$this->pathExists($new, $keys)) {
                $missing[] = implode('.', $keys);
            } else {
                $this->unsetPath($new, $keys);
            }
        }

        if ($missing) {
            throw new Exception(
                'Polje ne postoji: ' . implode(', ', $missing) . ' (za dodavanje koristi insert). Nista nije izmenjeno.'
            );
        }

        if ($ops['set'] || $ops['remove']) {
            $this->db->update($id, $new);
        }

        return $this->report($id, $old, $new, $img);
    }

    private function cmdDelete(string $args): array
    {
        [$id, $rest] = $this->splitId($args);
        $doc = $this->mustFind($id);

        // bez dodatnih argumenata: brise ceo dokument
        if ($rest === '') {
            if (!$this->db->delete($id)) {
                throw new Exception("Brisanje dokumenta $id nije uspelo.");
            }

            return [
                "Obrisan dokument $id (zajedno sa slikom, ako ju je imao).",
                'BIO JE: ' . $this->j($doc['data']),
            ];
        }

        // sa argumentima: brise samo navedena polja
        $old = $doc['data'];
        $new = $old;
        $missing = [];

        foreach ($this->tokenize($rest) as $tok) {
            $path = ltrim($tok['text'], '-');
            $keys = $this->keys($path);

            if (!$this->pathExists($new, $keys)) {
                $missing[] = $path;
            } else {
                $this->unsetPath($new, $keys);
            }
        }

        if ($missing) {
            throw new Exception('Polje ne postoji: ' . implode(', ', $missing) . '. Nista nije izmenjeno.');
        }

        $this->db->update($id, $new);

        return $this->report($id, $old, $new, null);
    }

    private function cmdShow(string $args): array
    {
        [$id] = $this->splitId($args);
        $doc = $this->mustFind($id);

        $out = [
            "ID:       $id",
            'Boja:     ' . $this->db->colorOfId($id),
            'Kreiran:  ' . ($doc['_created'] ?? '-'),
            'Izmenjen: ' . ($doc['_updated'] ?? '-'),
            'Slika:    ' . ($doc['_image'] ?? '-'),
            'Podaci:',
        ];

        $pretty = json_encode(
            $doc['data'],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );

        foreach (explode("\n", (string) $pretty) as $l) {
            $out[] = '  ' . $l;
        }

        return $out;
    }

    private function cmdList(string $filter): array
    {
        $f = strtolower(trim($filter));

        if ($f === '') {
            $docs = $this->db->all();
        } elseif (in_array($f, $this->db->colors(), true)) {
            $docs = $this->db->allByColor($f);
        } elseif (preg_match('/^[a-z0-9]{1,8}$/', $f)) {
            $docs = $this->db->findByPrefix($f);
        } else {
            throw new Exception('Filter moze biti boja, jedan karakter ili pocetak ID-a.');
        }

        if (!$docs) {
            return ['Nema dokumenata.'];
        }

        usort($docs, fn($a, $b) => strcmp($b['_created'] ?? '', $a['_created'] ?? ''));

        $out = [];

        foreach (array_slice($docs, 0, 50) as $doc) {
            $id = (string) ($doc['_id'] ?? '?');
            $data = $this->j($doc['data'] ?? []);

            if (function_exists('mb_strimwidth')) {
                $data = mb_strimwidth($data, 0, 70, '...');
            } elseif (strlen($data) > 70) {
                $data = substr($data, 0, 67) . '...';
            }

            $out[] = sprintf(
                '%s  %-9s %s %s',
                $id,
                (string) $this->db->colorOfId($id),
                empty($doc['_image']) ? '    ' : '[sl]',
                $data
            );
        }

        $shown = count($out);
        $out[] = '';
        $out[] = 'Prikazano ' . $shown . ' od ' . count($docs);

        return $out;
    }

    private function cmdCount(): array
    {
        $out = ['Ukupno dokumenata: ' . $this->db->count()];

        foreach ($this->db->statsByColor() as $color => $info) {
            $out[] = sprintf('  %-10s %d  (%s)', $color, $info['count'], implode(' ', $info['chars']));
        }

        return $out;
    }

    /* ------------------------------------------------------------------
     * POMOCNE FUNKCIJE
     * ------------------------------------------------------------------ */

    private function splitId(string $args): array
    {
        if (!preg_match('/^([a-z0-9]{8})(?:[\s,]+(.*))?$/s', $args, $m)) {
            throw new Exception('Prvi argument mora biti ID dokumenta (8 karaktera: a-z, 0-9).');
        }

        return [$m[1], trim($m[2] ?? '')];
    }

    private function mustFind(string $id): array
    {
        $doc = $this->db->find($id);

        if ($doc === null) {
            throw new Exception("Dokument $id ne postoji.");
        }

        if (!isset($doc['data']) || !is_array($doc['data'])) {
            $doc['data'] = [];
        }

        return $doc;
    }

    /*
     * Razdvaja argumente na polja za postavljanje, brisanje i sliku.
     */
    private function parseOps(string $rest): array
    {
        $ops = ['set' => [], 'remove' => [], 'image' => null];
        $rest = trim($rest);

        // ceo JSON objekat
        if ($rest !== '' && $rest[0] === '{') {
            $json = json_decode($rest, true);

            if (!is_array($json)) {
                throw new Exception('Neispravan JSON.');
            }

            foreach ($json as $k => $v) {
                $ops['set'][] = [[(string) $k], $v];
            }

            return $ops;
        }

        foreach ($this->tokenize($rest) as $tok) {
            $t = $tok['text'];

            if (stripos($t, '@slika=') === 0) {
                $ops['image'] = substr($t, 7);
                continue;
            }

            if (!$tok['quoted'] && $t !== '' && $t[0] === '-' && strpos($t, '=') === false) {
                $ops['remove'][] = $this->keys(substr($t, 1));
                continue;
            }

            $pos = strpos($t, '=');

            if ($pos === false || $pos === 0) {
                throw new Exception("Neispravan argument: $t (ocekivano polje=vrednost, -polje ili @slika=putanja)");
            }

            $ops['set'][] = [
                $this->keys(substr($t, 0, $pos)),
                $tok['quoted'] ? substr($t, $pos + 1) : $this->value(substr($t, $pos + 1)),
            ];
        }

        return $ops;
    }

    /*
     * Deli tekst na tokene; razmak i zarez razdvajaju, navodnici (" ili ')
     * povezuju tekst sa razmacima.
     */
    private function tokenize(string $s): array
    {
        $tokens = [];
        $cur = '';
        $has = false;
        $quoted = false;
        $q = null;
        $len = strlen($s);

        for ($i = 0; $i < $len; $i++) {
            $c = $s[$i];

            if ($q !== null) {
                if ($c === '\\' && $i + 1 < $len && ($s[$i + 1] === $q || $s[$i + 1] === '\\')) {
                    $cur .= $s[++$i];
                    continue;
                }

                if ($c === $q) {
                    $q = null;
                    continue;
                }

                $cur .= $c;
                continue;
            }

            // navodnik otvara tekst samo na pocetku tokena ili odmah posle "="
            if (($c === '"' || $c === "'") && ($cur === '' || substr($cur, -1) === '=')) {
                $q = $c;
                $has = true;
                $quoted = true;
                continue;
            }

            if (ctype_space($c) || $c === ',') {
                if ($has) {
                    $tokens[] = ['text' => $cur, 'quoted' => $quoted];
                }

                $cur = '';
                $has = false;
                $quoted = false;
                continue;
            }

            $cur .= $c;
            $has = true;
        }

        if ($q !== null) {
            throw new Exception('Navodnik nije zatvoren.');
        }

        if ($has) {
            $tokens[] = ['text' => $cur, 'quoted' => $quoted];
        }

        return $tokens;
    }

    private function keys(string $path): array
    {
        if (!preg_match('/^[\p{L}\p{N}_-]+(\.[\p{L}\p{N}_-]+)*$/u', $path)) {
            throw new Exception("Neispravan naziv polja: '$path'");
        }

        return explode('.', $path);
    }

    private function value(string $v)
    {
        if (preg_match('/^-?(0|[1-9]\d*)(\.\d+)?$/', $v)) {
            return $v + 0;
        }

        switch (strtolower($v)) {
            case 'true':  return true;
            case 'false': return false;
            case 'null':  return null;
        }

        return $v;
    }

    private function pathExists(array $a, array $keys): bool
    {
        foreach ($keys as $k) {
            if (!is_array($a) || !array_key_exists($k, $a)) {
                return false;
            }

            $a = $a[$k];
        }

        return true;
    }

    private function setPath(array &$a, array $keys, $value): void
    {
        $ref = &$a;
        $last = count($keys) - 1;

        foreach ($keys as $i => $k) {
            if ($i === $last) {
                $ref[$k] = $value;
                return;
            }

            if (array_key_exists($k, $ref)) {
                if (!is_array($ref[$k])) {
                    throw new Exception("'" . implode('.', array_slice($keys, 0, $i + 1)) . "' nije objekat, ne moze da sadrzi podpolja.");
                }
            } else {
                $ref[$k] = [];
            }

            $ref = &$ref[$k];
        }
    }

    private function unsetPath(array &$a, array $keys): void
    {
        $ref = &$a;
        $last = count($keys) - 1;

        foreach ($keys as $i => $k) {
            if ($i === $last) {
                unset($ref[$k]);
                return;
            }

            $ref = &$ref[$k];
        }
    }

    /*
     * Putanje slike: trazi se u ulaz/ i u folderu skripte (nikad izvan njega).
     */
    private function checkImage(?string $img): ?string
    {
        if ($img === null) {
            return null;
        }

        $img = trim($img);

        if ($img === '-') {
            return '-';
        }

        if ($img === '') {
            throw new Exception('@slika= nema putanju.');
        }

        $base = realpath($this->baseDir);

        foreach ([$this->inputDir . '/' . $img, $this->baseDir . '/' . $img, $img] as $candidate) {
            $real = realpath($candidate);

            if ($real !== false && is_file($real) && strpos($real, $base . DIRECTORY_SEPARATOR) === 0) {
                return $real;
            }
        }

        throw new Exception("Slika nije pronadjena: $img (stavi je u folder ulaz/).");
    }

    private function imageLines(string $id, ?string $img): array
    {
        if ($img === null) {
            return [];
        }

        try {
            if ($img === '-') {
                return [$this->db->removeImage($id) ? 'Slika uklonjena.' : 'Dokument nije imao sliku.'];
            }

            return ['Slika sacuvana: ' . $this->db->setImage($id, $img)];

        } catch (Throwable $e) {
            return ['UPOZORENJE: slika nije sacuvana: ' . $e->getMessage()];
        }
    }

    private function report(string $id, array $old, array $new, ?string $img): array
    {
        // samo slika, bez izmena podataka: nema potrebe za PRE/POSLE
        if ($old === $new && $img !== null) {
            return array_merge(
                ["Dokument $id (boja: " . $this->db->colorOfId($id) . ')'],
                $this->imageLines($id, $img)
            );
        }

        $out = [
            "Dokument $id (boja: " . $this->db->colorOfId($id) . ')',
            'PRE:    ' . $this->j($old),
            'POSLE:  ' . $this->j($new),
        ];

        $diff = $this->diff($old, $new);

        if ($diff) {
            $out[] = 'PROMENE:';
            $out = array_merge($out, $diff);
        } else {
            $out[] = '(nema promena u podacima)';
        }

        return array_merge($out, $this->imageLines($id, $img));
    }

    private function flatten(array $a, string $prefix = ''): array
    {
        $out = [];

        foreach ($a as $k => $v) {
            $key = $prefix === '' ? (string) $k : $prefix . '.' . $k;

            if (is_array($v) && $v !== [] && $v !== array_values($v)) {
                $out += $this->flatten($v, $key);
            } else {
                $out[$key] = $v;
            }
        }

        return $out;
    }

    private function diff(array $old, array $new): array
    {
        $a = $this->flatten($old);
        $b = $this->flatten($new);
        $lines = [];

        foreach ($b as $k => $v) {
            if (!array_key_exists($k, $a)) {
                $lines[] = "  + $k: " . $this->j($v);
            } elseif ($a[$k] !== $v) {
                $lines[] = "  ~ $k: " . $this->j($a[$k]) . ' -> ' . $this->j($v);
            }
        }

        foreach ($a as $k => $v) {
            if (!array_key_exists($k, $b)) {
                $lines[] = "  - $k: " . $this->j($v);
            }
        }

        return $lines;
    }

    private function j($v): string
    {
        $s = json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $s === false ? 'null' : $s;
    }
}
