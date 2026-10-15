<?php

class MiniNoSQL
{
    private string $path;
    private int $idLength = 8;
    private string $alphabet =
        'abcdefghijklmnopqrstuvwxyz0123456789';

    public function __construct(string $path = __DIR__ . '/data')
    {
        $this->path = rtrim($path, '/\\');

        if (!is_dir($this->path)) {
            mkdir($this->path, 0777, true);
        }
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

            $file = $this->getFile($id);

        } while (file_exists($file));

        return $id;
    }


    /*
     * PUTANJA DO DOKUMENTA
     */
    private function getFile(string $id): string
    {
        return $this->path . '/' . $id . '.json';
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

        $json = json_encode(
            $document,
            JSON_PRETTY_PRINT |
            JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES
        );

        if ($json === false) {
            throw new Exception('Greška pri JSON enkodovanju.');
        }

        file_put_contents(
            $this->getFile($id),
            $json,
            LOCK_EX
        );

        return $id;
    }


    /*
     * FIND
     */
    public function find(string $id): ?array
    {
        $file = $this->getFile($id);

        if (!file_exists($file)) {
            return null;
        }

        $json = file_get_contents($file);

        if ($json === false) {
            return null;
        }

        $document = json_decode($json, true);

        if (!is_array($document)) {
            return null;
        }

        return $document;
    }


    /*
     * UPDATE
     */
    public function update(string $id, array $data): bool
    {
        $file = $this->getFile($id);

        if (!file_exists($file)) {
            return false;
        }

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

        $json = json_encode(
            $document,
            JSON_PRETTY_PRINT |
            JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES
        );

        if ($json === false) {
            return false;
        }

        file_put_contents(
            $file,
            $json,
            LOCK_EX
        );

        return true;
    }


    /*
     * DELETE
     */
    public function delete(string $id): bool
    {
        $file = $this->getFile($id);

        if (!file_exists($file)) {
            return false;
        }

        return unlink($file);
    }


    /*
     * ALL
     */
    public function all(): array
    {
        $files = glob($this->path . '/*.json');

        if ($files === false) {
            return [];
        }

        $result = [];

        foreach ($files as $file) {

            $json = file_get_contents($file);

            if ($json === false) {
                continue;
            }

            $document = json_decode($json, true);

            if (is_array($document)) {
                $result[] = $document;
            }
        }

        return $result;
    }


    /*
     * COUNT
     */
    public function count(): int
    {
        $files = glob($this->path . '/*.json');

        if ($files === false) {
            return 0;
        }

        return count($files);
    }
}
