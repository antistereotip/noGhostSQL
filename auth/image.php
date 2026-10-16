<?php

/*
 * image.php?id=<id dokumenta>
 * Prikazuje sliku dokumenta, samo prijavljenom korisniku.
 * (Folder data/ je zakljucan, pa slike nisu dostupne direktnim linkom.)
 */

require __DIR__ . '/auth.php';
auth_require();

require __DIR__ . '/database.php';

$db = new MiniNoSQL(__DIR__ . '/data');

$image = $db->getImage((string) ($_GET['id'] ?? ''));

if ($image === null) {
    http_response_code(404);
    exit('Slika ne postoji.');
}

header('Content-Type: ' . $image['mime']);
header('Content-Length: ' . filesize($image['path']));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=3600');

readfile($image['path']);
