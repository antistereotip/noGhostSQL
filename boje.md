### Pretraga, indeksiranje i pohranjivanje putem boje:

Boja se ne čuva nigde. Računa se iz prvog karaktera ID-a, a prvi karakter ID-a je već ono što određuje u kom folderu je dokument.

Pravilo

Karakteri su poređani u nizu a-z0-9 (36 mesta, pozicije 0 do 35). Boja je ostatak pri deljenju pozicije sa 4:

pozicija % 4  →  0 = orangered, 1 = royalblue, 2 = seagreen, 3 = goldenrod

Tako je svaki 4. karakter orangered, svaki sledeći royalblue i tako ciklično. Svaka boja dobija tačno 9 karaktera:

Boja	        Karakteri
orangered	    a e i m q u y 2 6
royalblue	    b f j n r v z 3 7
seagreen	    c g k o s w 0 4 8
goldenrod	    d h l p t x 1 5 9

Primer

Dokument sa ID-em k3x9m2pa:

1. Prvi karakter je k, a to je pozicija 10 u nizu.
2. 10 % 4 = 2, dakle boja je seagreen.
3. Fajl je u folderu data/k/.

Kako pretraga po boji radi

Kad u index.php klikneš na boju (ili pozoveš allByColor('seagreen')), kod radi ovo:

1. bucketsByColor('seagreen') vrati 9 karaktera te boje: c g k o s w 0 4 8.
2. Čita se samo tih 9 foldera (data/c/, data/g/...), a ostalih 27 se ne dodiruje.
3. Dobijeni dokumenti se prikazuju kao jedan rezultat.

Boje su dakle drugi nivo iznad 36 foldera: 36 foldera je sitnija podela, a 4 boje su grupe od po 9 foldera.

Šta to znači u praksi

* Nema migracije. Pošto se boja izračunava, ne postoji ništa što bi trebalo ažurirati kad dodaš ili obrišeš dokument.
* Raspodela je približno ravnomerna, oko 25% dokumenata po boji, jer su ID-jevi nasumični.
* Pretraga po boji čita oko 4 puta manje fajlova od pretrage cele baze. Direktno traženje po ID-u (find) ne koristi boje, 
jer ionako ide pravo u pravi folder.
* Boje su i vizuelna oznaka: obojena tačka pored ID-a, obojena dugmad za 36 karaktera i 4 filtera.

Ako menjaš boje

Nazive možeš da promeniš u nizu $colors na vrhu klase u database.php. Menjanje samo naziva (npr. goldenrod u purple) 
ne dira raspored dokumenata. Ali ako promeniš broj boja ili njihov redosled, menja se i pripadnost: sa 3 boje bi se 
pravilo % 3 i svi karakteri bi se drugačije rasporedili, što je i dalje bez migracije, jer su folderi isti.
