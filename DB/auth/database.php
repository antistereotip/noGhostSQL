<?php

class MiniNoSQL
{
    private string $path;
    private int $idLength = 8;
    private string $alphabet = 'abcdefghijklmnopqrstuvwxyz0123456789';

    // 4 boje: svaki 4. karakter (po redosledu a-z, 0-9) ima istu boju
    private array $colors = ['orangered', 'royalblue', 'seagreen', 'goldenrod'];

    public function __construct(string $path = __DIR__ . '/data')
    {
        $this->path = rtrim($path, '/\\');

        if (!is_dir($this->path)) {
            mkdir($this->path, 0777, true);
        }

        // Zabrani direktan pristup JSON fajlovima preko browsera (Apache)
        $htaccess = $this->path . '/.htaccess';

        if (!file_exists($htaccess)) {
            @file_put_contents(
                $htaccess,
                "<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n" .
                "<IfModule !mod_authz_core.c>\n    Deny from all\n</IfModule>\n"
            );
        }

        // Kreira 36 bucket-a (a-z, 0-9) - po jedan folder za svaki prvi karakter ID-a
        foreach ($this->buckets() as $bucket) {
            $dir = $this->path . '/' . $bucket;

            if (!is_dir($dir)) {
                mkdir($dir, 0777, true);
            }
        }
    }

    /*
     * INDEKS: lista svih 36 bucket-a
     */
    public function buckets(): array
    {
        return str_split($this->alphabet);
    }

    /*
     * BOJE: lista od 4 boje
     */
    public function colors(): array
    {
        return $this->colors;
    }

    /*
     * BOJA KARAKTERA: pozicija u alfabetu % 4
     * a=orangered, b=royalblue, c=seagreen, d=goldenrod, e=orangered ...
     */
    public function colorOf(string $char): ?string
    {
        $char = strtolower($char);

        if (!$this->isValidBucket($char)) {
            return null;
        }

        $pos = strpos($this->alphabet, $char);

        return $this->colors[$pos % count($this->colors)];
    }

    /*
     * BOJA DOKUMENTA: odredjuje je prvi karakter ID-a
     */
    public function colorOfId(string $id): ?string
    {
        return $id === '' ? null : $this->colorOf($id[0]);
    }

    /*
     * MAPA: karakter => boja (svih 36)
     */
    public function colorMap(): array
    {
        $map = [];

        foreach ($this->buckets() as $bucket) {
            $map[$bucket] = $this->colorOf($bucket);
        }

        return $map;
    }

    /*
     * KARAKTERI JEDNE BOJE (9 komada)
     */
    public function bucketsByColor(string $color): array
    {
        $color = strtolower($color);

        return array_values(array_filter(
            $this->buckets(),
            fn($b) => $this->colorOf($b) === $color
        ));
    }

    /*
     * SVI DOKUMENTI JEDNE BOJE (iz 9 bucket-a)
     */
    public function allByColor(string $color): array
    {
        $result = [];

        foreach ($this->bucketsByColor($color) as $bucket) {
            foreach ($this->allByBucket($bucket) as $document) {
                $result[] = $document;
            }
        }

        return $result;
    }

    /*
     * BROJ DOKUMENATA JEDNE BOJE
     */
    public function countByColor(string $color): int
    {
        $total = 0;

        foreach ($this->bucketsByColor($color) as $bucket) {
            $total += $this->countByBucket($bucket);
        }

        return $total;
    }

    /*
     * STATISTIKA PO BOJAMA: boja => [karakteri, broj dokumenata]
     */
    public function statsByColor(): array
    {
        $stats = [];

        foreach ($this->colors as $color) {
            $stats[$color] = [
                'chars' => $this->bucketsByColor($color),
                'count' => $this->countByColor($color),
            ];
        }

        return $stats;
    }

    /*
     * VALIDACIJA ID-a (štiti i od path traversal napada)
     */
    private function isValidId(string $id): bool
    {
        return (bool) preg_match(
            '/^[a-z0-9]{' . $this->idLength . '}$/',
            $id
        );
    }

    private function isValidBucket(string $bucket): bool
    {
        return strlen($bucket) === 1
            && strpos($this->alphabet, $bucket) !== false;
    }

    /*
     * GENERISANJE ID-a
     */
    private function generateId(): string
    {
        do {
            $id = '';

            for ($i = 0; $i < $this->idLength; $i++) {
                $id .= $this->alphabet[
                    random_int(0, strlen($this->alphabet) - 1)
                ];
            }

        } while (file_exists($this->getFile($id)));

        return $id;
    }

    /*
     * PUTANJA DO DOKUMENTA: data/{prvi karakter}/{id}.json
     */
    private function getFile(string $id): string
    {
        return $this->path . '/' . $id[0] . '/' . $id . '.json';
    }

    /*
     * ATOMSKI UPIS (privremeni fajl pa rename)
     */
    private function writeDocument(string $file, array $document): bool
    {
        $json = json_encode(
            $document,
            JSON_PRETTY_PRINT |
            JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES
        );

        if ($json === false) {
            return false;
        }

        $tmp = $file . '.tmp';

        if (file_put_contents($tmp, $json, LOCK_EX) === false) {
            return false;
        }

        return rename($tmp, $file);
    }

    private function readFile(string $file): ?array
    {
        $json = @file_get_contents($file);

        if ($json === false) {
            return null;
        }

        $document = json_decode($json, true);

        return is_array($document) ? $document : null;
    }

    /*
     * INSERT
     */
    public function insert(array $data): string
    {
        $id = $this->generateId();

        $document = [
            '_id' => $id,
            'data' => $data,
            '_created' => date('Y-m-d H:i:s')
        ];

        if (!$this->writeDocument($this->getFile($id), $document)) {
            throw new Exception('Greška pri upisu dokumenta.');
        }

        return $id;
    }

    /*
     * FIND
     */
    public function find(string $id): ?array
    {
        if (!$this->isValidId($id)) {
            return null;
        }

        $file = $this->getFile($id);

        if (!file_exists($file)) {
            return null;
        }

        return $this->readFile($file);
    }

    /*
     * UPDATE
     */
    public function update(string $id, array $data): bool
    {
        $old = $this->find($id);

        if ($old === null) {
            return false;
        }

        $document = [
            '_id' => $id,
            'data' => $data,
            '_created' => $old['_created'] ?? date('Y-m-d H:i:s'),
            '_updated' => date('Y-m-d H:i:s')
        ];

        return $this->writeDocument($this->getFile($id), $document);
    }

    /*
     * DELETE
     */
    public function delete(string $id): bool
    {
        if (!$this->isValidId($id)) {
            return false;
        }

        $file = $this->getFile($id);

        if (!file_exists($file)) {
            return false;
        }

        return unlink($file);
    }

    /*
     * ALL - svi dokumenti iz svih 36 bucket-a
     */
    public function all(): array
    {
        $result = [];

        foreach ($this->buckets() as $bucket) {
            foreach ($this->allByBucket($bucket) as $document) {
                $result[] = $document;
            }
        }

        return $result;
    }

    /*
     * ALL BY BUCKET - svi dokumenti čiji ID počinje datim karakterom
     */
    public function allByBucket(string $bucket): array
    {
        $bucket = strtolower($bucket);

        if (!$this->isValidBucket($bucket)) {
            return [];
        }

        $files = glob($this->path . '/' . $bucket . '/*.json');

        if ($files === false) {
            return [];
        }

        $result = [];

        foreach ($files as $file) {
            $document = $this->readFile($file);

            if ($document !== null) {
                $result[] = $document;
            }
        }

        return $result;
    }

    /*
     * FIND BY PREFIX - pretraga po početku ID-a (1 do 8 karaktera)
     * Čita samo jedan bucket umesto cele baze.
     */
    public function findByPrefix(string $prefix): array
    {
        $prefix = strtolower($prefix);

        if (!preg_match('/^[a-z0-9]{1,' . $this->idLength . '}$/', $prefix)) {
            return [];
        }

        $files = glob($this->path . '/' . $prefix[0] . '/' . $prefix . '*.json');

        if ($files === false) {
            return [];
        }

        $result = [];

        foreach ($files as $file) {
            $document = $this->readFile($file);

            if ($document !== null) {
                $result[] = $document;
            }
        }

        return $result;
    }

    /*
     * COUNT - ukupan broj dokumenata
     */
    public function count(): int
    {
        $total = 0;

        foreach ($this->buckets() as $bucket) {
            $total += $this->countByBucket($bucket);
        }

        return $total;
    }

    /*
     * COUNT BY BUCKET
     */
    public function countByBucket(string $bucket): int
    {
        $bucket = strtolower($bucket);

        if (!$this->isValidBucket($bucket)) {
            return 0;
        }

        $files = glob($this->path . '/' . $bucket . '/*.json');

        return $files === false ? 0 : count($files);
    }

    /*
     * STATS - broj dokumenata po svakom od 36 bucket-a
     */
    public function stats(): array
    {
        $stats = [];

        foreach ($this->buckets() as $bucket) {
            $stats[$bucket] = $this->countByBucket($bucket);
        }

        return $stats;
    }

    /*
     * MIGRATE - prebacuje stare dokumente iz data/ u data/{bucket}/
     * Pokreni jednom ako već imaš podatke u starom formatu.
     * Vraća broj prebačenih fajlova.
     */
    public function migrate(): int
    {
        $files = glob($this->path . '/*.json');

        if ($files === false) {
            return 0;
        }

        $moved = 0;

        foreach ($files as $file) {
            $id = basename($file, '.json');

            if (!$this->isValidId($id)) {
                continue;
            }

            if (rename($file, $this->getFile($id))) {
                $moved++;
            }
        }

        return $moved;
    }
}
