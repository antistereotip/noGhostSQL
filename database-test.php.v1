<?php

require_once 'database.php';

$db = new MiniNoSQL();


// DODAVANJE
$id = $db->insert([
    'name' => 'Marko',
    'age' => 44,
    'city' => 'Beograd'
]);

echo "Novi ID: " . $id . "<br><br>";


// ČITANJE
$user = $db->find($id);

echo '<pre>';
print_r($user);
echo '</pre>';


// IZMENA
$db->update($id, [
    'name' => 'Marko',
    'age' => 45,
    'city' => 'Beograd'
]);


// PONOVO ČITANJE
$user = $db->find($id);

echo '<pre>';
print_r($user);
echo '</pre>';


// BROJ ZAPISA
echo "Ukupno zapisa: " . $db->count() . "<br>";


// BRISANJE
// $db->delete($id);
