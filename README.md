# noGhostSQL
### Lightweight Database
#### https://github.com/antistereotip/nogGhostSQL/blob/main/base.php
#### https://usk.rs/RP/DB/base.php
# Priprema se UI za konzolu
### Na pocetku svake radne skripte u php fajlu postoji ovo: die('up'); //odkomentovati da bi skripta radila
## Primer fajla sa komandama. Linije koje pocinju sa # su komentari.
#### Slike stavi u folder _files/ i navedi samo ime fajla, npr. @slika=marko.jpg
#### Novi dokumenti (create)
##### create ime="Marko Markovic" godine=25 grad="Novi Sad"
##### create {"ime":"Ana","adresa":{"grad":"Beograd","ulica":"Knez Mihailova"}}
#### Lista svih dokumenata, da vidis njihove ID-eve list
#### Sledece komande zahtevaju stvaran ID iz liste iznad.
#### Skini # sa pocetka linije i zameni k3x9m2pa pravim ID-em.
#### insert: dodaje NOVA polja (greska ako polje vec postoji)
#### insert k3x9m2pa email=marko@primer.rs telefon="060123456"
#### edit: menja POSTOJECA polja, -polje brise polje, @slika dodaje/zamenjuje sliku
#### edit k3x9m2pa godine=26 -telefon   ---   isti index za podatke u json-u
#### edit k3x9m2pa @slika=marko.jpg   ---   isti index za sliku
#### delete: samo polja, ili ceo dokument
#### delete k3x9m2pa email
#### delete k3x9m2pa
## konzola.php
##### create ime="Marko" godine=23 @slika=marko.jpg
##### insert k2x9m2pa email=marko@primer.rs
##### edit k2x9m2pa godine=28
##### delete k2x9m2pa
##### list
