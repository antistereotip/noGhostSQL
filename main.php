
<?php
declare(strict_types=1);

/**
 * MiniNoSQL v2
 * File-based dokumentna baza podataka.
 *
 * API:
 * insert(array $data): string
 * find(string $id): ?array
 * findBy(string $field, mixed $value): array
 * update(string $id, array $data): bool
 * delete(string $id): bool
 * all(): array
 * count(): int
 * createIndex(string $field): bool
 * rebuildIndexes(): int
 * indexes(): array
 */

class MiniNoSQL
{
    private string $path;
    private string $recordsPath;
    private string $indexesPath;
    private string $lockPath;

    private int $idLength = 8;
    private string $alphabet =
        'abcdefghijklmnopqrstuvwxyz0123456789';

    public function __construct(?string $path = null)
    {
        $this->path = rtrim(
            $path ?? (__DIR__ . '/data'),
            DIRECTORY_SEPARATOR
        );

        $this->recordsPath = $this->path . '/records';
        $this->indexesPath = $this->path . '/indexes';
        $this->lockPath = $this->path . '/database.lock';

        foreach ([
            $this->path,
            $this->recordsPath,
            $this->indexesPath
        ] as $directory) {
            if (
                !is_dir($directory) &&
                !mkdir($directory, 0750, true) &&
                !is_dir($directory)
            ) {
                throw new RuntimeException(
                    "Ne mogu da kreiram direktorijum: $directory"
                );
            }
        }

        if (!is_file($this->lockPath)) {
            $handle = fopen($this->lockPath, 'c');

            if ($handle === false) {
                throw new RuntimeException(
                    'Ne mogu da kreiram lock fajl.'
                );
            }

            fclose($handle);
        }

        $this->withLock(
            LOCK_EX,
            function (): void {
                $this->loadIndexDefinitions();
            }
        );
    }

    /* =========================================
       ZAKLJUČAVANJE
    ========================================= */

    private function withLock(
        int $mode,
        callable $callback
    ): mixed {
        $handle = fopen($this->lockPath, 'c');

        if ($handle === false) {
            throw new RuntimeException(
                'Ne mogu da otvorim lock fajl.'
            );
        }

        try {
            if (!flock($handle, $mode)) {
                throw new RuntimeException(
                    'Zaključavanje baze nije uspelo.'
                );
            }

            return $callback();
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    /* =========================================
       BEZBEDAN JSON UPIS
    ========================================= */

    private function encode(array $value): string
    {
        return json_encode(
            $value,
            JSON_PRETTY_PRINT |
            JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES |
            JSON_THROW_ON_ERROR
        );
    }

    private function atomicWrite(
        string $file,
        string $contents
    ): void {
        $temporary = tempnam(
            dirname($file),
            '.nosql-tmp-'
        );

        if ($temporary === false) {
            throw new RuntimeException(
                'Ne mogu da napravim privremeni fajl.'
            );
        }

        try {
            if (
                file_put_contents(
                    $temporary,
                    $contents,
                    LOCK_EX
                ) === false
            ) {
                throw new RuntimeException(
                    'Upis podataka nije uspeo.'
                );
            }

            if (!rename($temporary, $file)) {
                throw new RuntimeException(
                    'Zamena fajla nije uspela.'
                );
            }
        } finally {
            if (is_file($temporary)) {
                @unlink($temporary);
            }
        }
    }

    /* =========================================
       ID SISTEM
    ========================================= */

    private function validId(string $id): bool
    {
        return strlen($id) === $this->idLength
            && strspn(
                $id,
                $this->alphabet
            ) === $this->idLength;
    }

    private function recordFile(string $id): string
    {
        if (!$this->validId($id)) {
            throw new InvalidArgumentException(
                'Neispravan ID dokumenta.'
            );
        }

        return $this->recordsPath
            . '/'
            . $id
            . '.json';
    }

    private function generateId(): string
    {
        do {
            $id = '';

            for ($i = 0; $i < $this->idLength; $i++) {
                $position = random_int(
                    0,
                    strlen($this->alphabet) - 1
                );

                $id .= $this->alphabet[$position];
            }
        } while (is_file($this->recordFile($id)));

        return $id;
    }

    /* =========================================
       ČITANJE DOKUMENTA
    ========================================= */

    private function readRecord(string $id): ?array
    {
        $file = $this->recordFile($id);

        if (!is_file($file)) {
            return null;
        }

        $json = file_get_contents($file);

        if ($json === false) {
            throw new RuntimeException(
                'Ne mogu da pročitam dokument.'
            );
        }

        try {
            $record = json_decode(
                $json,
                true,
                512,
                JSON_THROW_ON_ERROR
            );
        } catch (JsonException $exception) {
            throw new RuntimeException(
                'Dokument sadrži neispravan JSON.',
                0,
                $exception
            );
        }

        if (
            !is_array($record) ||
            ($record['_id'] ?? null) !== $id ||
            !isset($record['data']) ||
            !is_array($record['data'])
        ) {
            throw new RuntimeException(
                'Struktura dokumenta nije ispravna.'
            );
        }

        return $record;
    }

    private function recordFiles(): array
    {
        $files = glob(
            $this->recordsPath . '/*.json'
        );

        return $files === false ? [] : $files;
    }

    /* =========================================
       DEFINICIJE INDEKSA
    ========================================= */

    private function loadIndexDefinitions(): array
    {
        $file = $this->indexesPath
            . '/_definitions.json';

        if (!is_file($file)) {
            $this->atomicWrite(
                $file,
                $this->encode(['fields' => []])
            );
        }

        $json = file_get_contents($file);

        if ($json === false) {
            throw new RuntimeException(
                'Ne mogu da pročitam definicije indeksa.'
            );
        }

        $definitions = json_decode($json, true);

        if (
            !is_array($definitions) ||
            !isset($definitions['fields']) ||
            !is_array($definitions['fields'])
        ) {
            throw new RuntimeException(
                'Definicije indeksa su oštećene.'
            );
        }

        return array_values(
            array_unique(
                array_filter(
                    $definitions['fields'],
                    static fn($field) =>
                        is_string($field) &&
                        $field !== ''
                )
            )
        );
    }

    private function saveIndexDefinitions(
        array $fields
    ): void {
        $this->atomicWrite(
            $this->indexesPath . '/_definitions.json',
            $this->encode([
                'fields' => array_values(
                    array_unique($fields)
                )
            ])
        );
    }

    private function indexFile(string $field): string
    {
        return $this->indexesPath
            . '/'
            . hash('sha256', $field)
            . '.json';
    }

    private function valueKey(mixed $value): ?string
    {
        if (!is_scalar($value) && $value !== null) {
            return null;
        }

        return json_encode(
            $value,
            JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES |
            JSON_THROW_ON_ERROR
        );
    }

    /* =========================================
       ODRŽAVANJE INDEKSA
    ========================================= */

    private function changeIndex(
        string $field,
        mixed $value,
        string $id,
        bool $add
    ): void {
        $key = $this->valueKey($value);

        if ($key === null) {
            return;
        }

        $file = $this->indexFile($field);
        $index = [];

        if (is_file($file)) {
            $json = file_get_contents($file);

            if ($json === false) {
                throw new RuntimeException(
                    'Ne mogu da pročitam indeks.'
                );
            }

            $decoded = json_decode($json, true);

            if (!is_array($decoded)) {
                throw new RuntimeException(
                    'Indeks je oštećen.'
                );
            }

            $index = $decoded;
        }

        $ids = $index[$key] ?? [];

        if ($add) {
            if (!in_array($id, $ids, true)) {
                $ids[] = $id;
            }

            $index[$key] = array_values($ids);
        } else {
            $ids = array_values(
                array_filter(
                    $ids,
                    static fn($existingId) =>
                        $existingId !== $id
                )
            );

            if ($ids === []) {
                unset($index[$key]);
            } else {
                $index[$key] = $ids;
            }
        }

        $this->atomicWrite(
            $file,
            $this->encode($index)
        );
    }

    private function syncIndexes(
        string $id,
        array $oldData,
        array $newData
    ): void {
        foreach ($this->loadIndexDefinitions() as $field) {
            $oldExists = array_key_exists(
                $field,
                $oldData
            );

            $newExists = array_key_exists(
                $field,
                $newData
            );

            $oldKey = $oldExists
                ? $this->valueKey($oldData[$field])
                : null;

            $newKey = $newExists
                ? $this->valueKey($newData[$field])
                : null;

            if (
                $oldExists &&
                (!$newExists || $oldKey !== $newKey)
            ) {
                $this->changeIndex(
                    $field,
                    $oldData[$field],
                    $id,
                    false
                );
            }

            if (
                $newExists &&
                (!$oldExists || $oldKey !== $newKey)
            ) {
                $this->changeIndex(
                    $field,
                    $newData[$field],
                    $id,
                    true
                );
            }
        }
    }

    /* =========================================
       INSERT
    ========================================= */

    public function insert(array $data): string
    {
        return $this->withLock(
            LOCK_EX,
            function () use ($data): string {
                $id = $this->generateId();

                $document = [
                    '_id' => $id,
                    'data' => $data,
                    '_created' => date(DATE_ATOM)
                ];

                $this->atomicWrite(
                    $this->recordFile($id),
                    $this->encode($document)
                );

                try {
                    $this->syncIndexes(
                        $id,
                        [],
                        $data
                    );
                } catch (Throwable $exception) {
                    @unlink($this->recordFile($id));

                    // Obnovi indekse iz postojećih dokumenata.
                    foreach ($this->loadIndexDefinitions() as $field) {
                        $this->rebuildIndexLocked($field);
                    }

                    throw $exception;
                }

                return $id;
            }
        );
    }

    /* =========================================
       FIND PO ID-u
    ========================================= */

    public function find(string $id): ?array
    {
        return $this->withLock(
            LOCK_SH,
            fn() => $this->readRecord($id)
        );
    }

    /* =========================================
       UPDATE
    ========================================= */

    public function update(
        string $id,
        array $data
    ): bool {
        return $this->withLock(
            LOCK_EX,
            function () use ($id, $data): bool {
                $old = $this->readRecord($id);

                if ($old === null) {
                    return false;
                }

                $document = [
                    '_id' => $id,
                    'data' => $data,
                    '_created' =>
                        $old['_created'] ?? date(DATE_ATOM),
                    '_updated' => date(DATE_ATOM)
                ];

                $this->atomicWrite(
                    $this->recordFile($id),
                    $this->encode($document)
                );

                try {
                    $this->syncIndexes(
                        $id,
                        $old['data'],
                        $data
                    );
                } catch (Throwable $exception) {
                    // Pokušaj da vratiš staro stanje dokumenta
                    // i obnoviš indekse.
                    $this->atomicWrite(
                        $this->recordFile($id),
                        $this->encode($old)
                    );

                    foreach ($this->loadIndexDefinitions() as $field) {
                        $this->rebuildIndexLocked($field);
                    }

                    throw $exception;
                }

                return true;
            }
        );
    }

    /* =========================================
       DELETE
    ========================================= */

    public function delete(string $id): bool
    {
        return $this->withLock(
            LOCK_EX,
            function () use ($id): bool {
                $old = $this->readRecord($id);

                if ($old === null) {
                    return false;
                }

                $this->syncIndexes(
                    $id,
                    $old['data'],
                    []
                );

                if (!unlink($this->recordFile($id))) {
                    throw new RuntimeException(
                        'Brisanje dokumenta nije uspelo.'
                    );
                }

                return true;
            }
        );
    }

    /* =========================================
       SVI DOKUMENTI
    ========================================= */

    public function all(): array
    {
        return $this->withLock(
            LOCK_SH,
            function (): array {
                $result = [];

                foreach ($this->recordFiles() as $file) {
                    $id = basename($file, '.json');
                    $record = $this->readRecord($id);

                    if ($record !== null) {
                        $result[] = $record;
                    }
                }

                return $result;
            }
        );
    }

    public function count(): int
    {
        return $this->withLock(
            LOCK_SH,
            fn(): int => count($this->recordFiles())
        );
    }

    /* =========================================
       KREIRANJE INDEKSA
    ========================================= */

    public function createIndex(string $field): bool
    {
        if (
            $field === '' ||
            str_starts_with($field, '_')
        ) {
            throw new InvalidArgumentException(
                'Unesi validno ime polja.'
            );
        }

        return $this->withLock(
            LOCK_EX,
            function () use ($field): bool {
                $fields = $this->loadIndexDefinitions();

                $exists = in_array(
                    $field,
                    $fields,
                    true
                );

                if (!$exists) {
                    $fields[] = $field;
                    $this->saveIndexDefinitions($fields);
                }

                $this->rebuildIndexLocked($field);

                return !$exists;
            }
        );
    }

    private function rebuildIndexLocked(
        string $field
    ): void {
        $index = [];

        foreach ($this->recordFiles() as $file) {
            $id = basename($file, '.json');
            $record = $this->readRecord($id);

            if (
                $record === null ||
                !array_key_exists(
                    $field,
                    $record['data']
                )
            ) {
                continue;
            }

            $key = $this->valueKey(
                $record['data'][$field]
            );

            if ($key !== null) {
                $index[$key] ??= [];
                $index[$key][] = $id;
            }
        }

        $this->atomicWrite(
            $this->indexFile($field),
            $this->encode($index)
        );
    }

    public function rebuildIndexes(): int
    {
        return $this->withLock(
            LOCK_EX,
            function (): int {
                $fields = $this->loadIndexDefinitions();

                foreach ($fields as $field) {
                    $this->rebuildIndexLocked($field);
                }

                return count($fields);
            }
        );
    }

    /* =========================================
       FIND BY INDEKS
    ========================================= */

    public function findBy(
        string $field,
        mixed $value
    ): array {
        return $this->withLock(
            LOCK_SH,
            function () use ($field, $value): array {
                if (
                    !in_array(
                        $field,
                        $this->loadIndexDefinitions(),
                        true
                    )
                ) {
                    throw new InvalidArgumentException(
                        "Polje '$field' nema indeks. " .
                        'Prvo pozovi createIndex().'
                    );
                }

                $key = $this->valueKey($value);

                if ($key === null) {
                    return [];
                }

                $file = $this->indexFile($field);

                if (!is_file($file)) {
                    return [];
                }

                $json = file_get_contents($file);

                if ($json === false) {
                    throw new RuntimeException(
                        'Ne mogu da pročitam indeks.'
                    );
                }

                $index = json_decode($json, true);

                if (!is_array($index)) {
                    throw new RuntimeException(
                        'Indeks je oštećen.'
                    );
                }

                $results = [];

                foreach ($index[$key] ?? [] as $id) {
                    if (!is_string($id)) {
                        continue;
                    }

                    $record = $this->readRecord($id);

                    if (
                        $record !== null &&
                        array_key_exists(
                            $field,
                            $record['data']
                        ) &&
                        $this->valueKey(
                            $record['data'][$field]
                        ) === $key
                    ) {
                        $results[] = $record;
                    }
                }

                return $results;
            }
        );
    }

    /* =========================================
       SPISAK INDEKSA
    ========================================= */

    public function indexes(): array
    {
        return $this->withLock(
            LOCK_SH,
            fn(): array => $this->loadIndexDefinitions()
        );
    }
}

