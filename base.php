<style>
    :root { --bg:#f5f6f8; --card:#fff; --text:#1d2330; --muted:#6b7280; --line:#e2e5ea; --accent:#2563eb; --danger:#dc2626; --ok:#15803d; }
    @media (prefers-color-scheme: dark) {
        :root { --bg:#12151b; --card:#1b1f27; --text:#e6e9ef; --muted:#9aa3b2; --line:#2c323d; --accent:#60a5fa; --danger:#f87171; --ok:#4ade80; }
    }
    * { box-sizing: border-box; }
    body { margin:1%; padding: 1%; pant-family: system-ui, sans-serif; background:var(--bg); color:var(--text); }
    .wrap { max-width: 960px; margin: 0 auto; padding: 24px 16px; }
    h1 { margin: 0 0 4px; font-size: 24px; }
    .sub { color: var(--muted); margin-bottom: 20px; }
    .card { background: var(--card); border: 1px solid var(--line); border-radius: 10px; padding: 16px; margin-bottom: 16px; }
    textarea, input, select { width:100%; padding:8px 10px; border:1px solid var(--line); border-radius:6px; background:var(--bg); color:var(--text); font: inherit; }
    textarea { min-height: 120px; font-family: ui-monospace, monospace; font-size: 14px; }
    button, .btn { display:inline-block; padding:8px 14px; border:0; border-radius:6px; background:var(--accent); color:#fff; font:inherit; cursor:pointer; text-decoration:none; }
    .btn-sec { background: transparent; color: var(--text); border: 1px solid var(--line); }
    .btn-danger { background: var(--danger); }
    .btn-sm { padding: 4px 10px; font-size: 13px; }
    .row { display:flex; gap:8px; flex-wrap:wrap; align-items:center; }
    .row > * { flex: 1 1 120px; }
    .row > button, .row > .btn { flex: 0 0 auto; }
    .flash { padding: 10px 14px; border-radius: 8px; margin-bottom: 16px; border: 1px solid var(--line); }
    .flash.ok { color: var(--ok); } .flash.err { color: var(--danger); }
    .hint { color: var(--muted); font-size: 13px; margin: 6px 0 10px; }
    .doc { border-top: 1px solid var(--line); padding: 12px 0; }
    .doc:first-child { border-top: 0; }
    .meta { color: var(--muted); font-size: 13px; }
    .id { font-family: ui-monospace, monospace; font-weight: 600; }
    pre { margin: 8px 0; padding: 10px; background: var(--bg); border-radius: 6px; overflow-x: auto; font-size: 13px; }
    form.inline { display:inline; }
    .buckets { display:flex; flex-wrap:wrap; gap:4px; margin-top:10px; }
    .buckets a { padding: 2px 8px; border:2px solid var(--c); border-radius:4px; color:var(--c); text-decoration:none; font-family: ui-monospace, monospace; font-size: 13px; font-weight: 600; }
    .buckets a.on { background: var(--c); color:#fff; }
    .buckets a.empty { opacity: .45; }
    .colors { display:flex; flex-wrap:wrap; gap:8px; margin-top:12px; }
    .colors a { padding: 4px 12px; border-radius: 999px; border:2px solid var(--c); color:var(--c); text-decoration:none; font-size: 13px; font-weight: 600; }
    .colors a.on { background: var(--c); color:#fff; }
    .dot { display:inline-block; width:10px; height:10px; border-radius:50%; background:var(--c); margin-right:6px; }
</style>


<div>
<pre>
<a href="https://usk.rs/old-index.html" style="text-deocration: none; color: royalblue;">Vrati se na Početnu Strana, Folder DB je Redirektovan</a>
</pre>

<pre>
<h2>Welcome to Future. The noGhostSQL is highly scaled open source No SQL Database.</h2>
</pre>

<pre>
This is the noGhostSQL (base.php) database documentation. It is a non-relational database. 
The database simply indexes the data and places it in the /data folder as .json files. We 
are currently working on optimizing the engine itself. Stay tuned. In no case should we 
disrupt the logic of the No SQL database. It now works very fast and reliably with these 
features. We recommend that you use the Github repo for each update. Use the files *-gui.php 
only as a test and be sure to delete them from the database after use or protect them with 
a password so that only you can access them.
</pre>

<pre>
Welcome to the most powerful database ever devised. Engine, like MVP, is written in PHP, which 
I intend to later change to ANSI C. With this database, you can be very satisfied with how 
quickly it collects data and how it manipulates it.
</pre>

<pre>
No matter how much data there is in one ID, it does not change the structure, but it can increase 
the size. For example, there are ten json entries in one id, and five json entries in the other. 
However, the database is as big as it can have IDs. This base indexing with 36 characters has a 
total of index combinations (IDs): 36⁸ = 2,821,109,907,456. That means almost three trillion, 
which gives it the ability to address the entire planet and orbit and to have another two 
trillion left ...
</pre>

<pre>
No SQL Database - No SQL Baza, 
Name: noGhostSQL, Engine language: PHP

<h3>Github Repo</h3>
<a href="https://github.com/antistereotip/noGhostSQL">https://github.com/antistereotip/noGhostSQL</a><br>
12 Files
3 folders - /data
Everything on repo is open source, free to use, 
modify and redistribute under MIT License.<br>
KISS (Keep It Simple Stupid).
</pre>

<pre>
<a href="https://usk.rs/RP/" style="text-deocration: none; color: royalblue;">Retro Port</a>
<a href="https://usk.rs/RP/0/?akcija=" style="text-deocration: none; color: royalblue;">Unesi Komandu</a>
<a href="https://usk.rs/bbsx/" style="text-deocration: none; color: royalblue;">Evo Malo Ezoterije</a>
<a href="#" style="text-deocration: none; color: royalblue;">Dalje nećes proći ...</a>
</pre>

<pre>
For premium users: <a href="https://usk.rs/RP/DB/nosql/">https://usk.rs/RP/DB/nosql/</a>. After purchasing a package of our services, you get one 
hash file that you run inside the auth.php folder and generate a password that you insert into auth.php 
and then everything is ready to log in with the desired password. You have many new features in the GUI 
and you can literally manipulate any data just by handling it within the graphical environment, and you 
also have a console, console.php, through which you can use commands to manipulate data.
</pre>


<pre>
The total number of possible IDs is 36 characters
≈ 1.03 × 10⁵⁶
It is a number with 57 digits.
For comparison: 8 character ID
36⁸ = 2,821,109,907,456
About 2.82 trillion combinations.
</pre>

<pre>
The indexing logic is as follows: What I have created is not an index in the classical 
sense (as in MySQL or MongoDB), but dividing files into folders by the first character 
of the ID. The folder name is actually the "address" of the document.
How it works:
Each document receives a random ID of 8 characters from the set a-z 0-9, eg. k3x9m2pa. 
The first character of the ID determines which folder the document goes to:
</pre>

<pre>
alfa-base.php
alfa-gui.php
data/
├── a/
|   └── aulnlnbo.json   ← first character is a
├── b/
├── c/
├── ...
├── g/
│   └── gxi6uggz.json   ← first character is g
├── h/
├── i/
├── ...
├── w
│   └── <b style="color: red;">w</b>xer45gz.json <b style="color: red;">w</b>fdr5rgz.json <b style="color: red;">w</b>ert0tgz.json ... <b style="color: red;">alfa-gui.php?bucket=w</b>
└── 9/
</pre>

<pre>
<h3>The database is designed to be simple and easy to use.</h3>
</pre>

<pre>
Method:                     Intended use:<br>
insert($data)               Document entry
find($id)                   Find by ID
findBy($field, $value)      Search by field
all()                       Load all documents
update($id, $data)          Document modification
delete($id)                 Delete a document
count()                     Counting documents
createIndex($field)         Create an index
indexes()                   View indexes
rebuildIndexes()            Rebuilding indexes
</pre>

<pre>
Important: Use the same base directory in all scripts. If one page opens new 
MiniNoSQL(__DIR__ . '/data') and the other uses a different directory, the pages 
will not work on the same data set.
</pre>

<pre>
After the completed modification in the database, the result looks like this:
</pre>

<pre>
<code>
DEMO: created test document with ID sg9wpyfw

=== BEFORE CHANGE (sg9wpyfw) ===
{
    "_id": "sg9wpyfw",
    "data": {
        "name": "Marko",
        "years": 25,
        "city": "Belgrade",
        "phone": "060123456"
    },
    "_created": "2026-10-15 13:45:13"
}

=== AFTER MODIFICATION (sg9wpyfw) ===
{
    "_id": "sg9wpyfw",
    "data": {
        "name": "Marko",
        "years": 26,
        "city": "Novi Sad",
        "email": "marko@primer.rs"
    },
    "_created": "2026-10-15 13:45:13",
    "_updated": "2026-10-15 13:45:13"
}

=== CHANGES ===
  ~ years: 25 -> 26
  ~ city: "Belgrade" -> "Novi Sad"
  + email: "marko@primer.rs"
  - phone: "060123456"
</code>
</pre>

<pre>
<h3>Indexing by Color</h3>
</pre>

<pre>
We don't just index by character, we also have indexing by color, which is new in 
the IT world. It seems to me that noGhostSQL has its own logic with color indexing. 
An algorithm is trained to index by color. The ML model works on coding the flags 
themselves by color data. Indexing by colored flags has now been added. Nine 
combinations on every fourth suit. Every fourth suit is the same, orangered, 
has 9 half octets, Nine nibbles. A huge number of entries are allowed. This 
is the base for huge projects that need clear base logic and need speed. It 
will look like this in the GUI:
</pre>

<div class="buckets">
    <a href="?bucket=a" style="--c: orangered" class=" " title="1 dokumenata">a <small>1</small></a>
    <a href="?bucket=b" style="--c: royalblue" class=" empty" title="0 dokumenata">b <small>0</small></a>
    <a href="?bucket=c" style="--c: seagreen" class=" empty" title="0 dokumenata">c <small>0</small></a>
    <a href="?bucket=d" style="--c: goldenrod" class=" empty" title="0 dokumenata">d <small>0</small></a>
    <a href="?bucket=e" style="--c: orangered" class=" " title="1 dokumenata">e <small>1</small></a>
    <a href="?bucket=f" style="--c: royalblue" class=" empty" title="0 dokumenata">f <small>0</small></a>
    <a href="?bucket=g" style="--c: seagreen" class=" " title="1 dokumenata">g <small>1</small></a>
    <a href="?bucket=h" style="--c: goldenrod" class=" " title="1 dokumenata">h <small>1</small></a>
    <a href="?bucket=i" style="--c: orangered" class=" empty" title="0 dokumenata">i <small>0</small></a><br>

    <a href="?bucket=j" style="--c: royalblue" class=" empty" title="0 dokumenata">j <small>0</small></a>
    <a href="?bucket=k" style="--c: seagreen" class=" empty" title="0 dokumenata">k <small>0</small></a>
    <a href="?bucket=l" style="--c: goldenrod" class=" empty" title="0 dokumenata">l <small>0</small></a>
    <a href="?bucket=m" style="--c: orangered" class=" empty" title="0 dokumenata">m <small>0</small></a>
    <a href="?bucket=n" style="--c: royalblue" class=" empty" title="0 dokumenata">n <small>0</small></a>
    <a href="?bucket=o" style="--c: seagreen" class=" empty" title="0 dokumenata">o <small>0</small></a>
    <a href="?bucket=p" style="--c: goldenrod" class=" empty" title="0 dokumenata">p <small>0</small></a>
    <a href="?bucket=q" style="--c: orangered" class=" empty" title="0 dokumenata">q <small>0</small></a>
    <a href="?bucket=r" style="--c: royalblue" class=" empty" title="0 dokumenata">r <small>0</small></a><br>

    <a href="?bucket=s" style="--c: seagreen" class=" empty" title="0 dokumenata">s <small>0</small></a>
    <a href="?bucket=t" style="--c: goldenrod" class=" empty" title="0 dokumenata">t <small>0</small></a>
    <a href="?bucket=u" style="--c: orangered" class=" empty" title="0 dokumenata">u <small>0</small></a>
    <a href="?bucket=v" style="--c: royalblue" class=" empty" title="0 dokumenata">v <small>0</small></a>
    <a href="?bucket=w" style="--c: seagreen" class=" " title="1 dokumenata">w <small>1</small></a>
    <a href="?bucket=x" style="--c: goldenrod" class=" empty" title="0 dokumenata">x <small>0</small></a>
    <a href="?bucket=y" style="--c: orangered" class=" " title="1 dokumenata">y <small>1</small></a>
    <a href="?bucket=z" style="--c: royalblue" class=" " title="1 dokumenata">z <small>1</small></a>
    <a href="?bucket=0" style="--c: seagreen" class=" empty" title="0 dokumenata">0 <small>0</small></a><br>

    <a href="?bucket=1" style="--c: goldenrod" class=" empty" title="0 dokumenata">1 <small>0</small></a>
    <a href="?bucket=2" style="--c: orangered" class=" empty" title="0 dokumenata">2 <small>0</small></a>
    <a href="?bucket=3" style="--c: royalblue" class=" empty" title="0 dokumenata">3 <small>0</small></a>
    <a href="?bucket=4" style="--c: seagreen" class=" empty" title="0 dokumenata">4 <small>0</small></a>
    <a href="?bucket=5" style="--c: goldenrod" class=" " title="1 dokumenata">5 <small>1</small></a>
    <a href="?bucket=6" style="--c: orangered" class=" " title="1 dokumenata">6 <small>1</small></a>
    <a href="?bucket=7" style="--c: royalblue" class=" " title="1 dokumenata">7 <small>1</small></a>
    <a href="?bucket=8" style="--c: seagreen" class=" empty" title="0 dokumenata">8 <small>0</small></a>
    <a href="?bucket=9" style="--c: goldenrod" class=" empty" title="0 dokumenata">9 <small>0</small></a><br>
</div>
    
<pre>
The colors that serve as flags for indexing are actually a horizontal layer of indexing 
because they bring all the colors to the surface and if the data hits vertically, the speed is less than a millisecond.
</pre>

<pre>
When you have a graphical database environment in front of you, any *-gui.php 
file on Github, protect it with authentication so that only you can access it, 
we gave the basic GUI type <b style="color: red;">alfa-gui.php</b> and <b style="color: red;">beta-gui.php</b> as a gift to the community, 
now you can help to create an admin GUI environment with authentication so that 
data can be managed in peace without fear of data theft. Better and faster than 
any phpmyadmin environment or GUI like that.
</pre>

<pre>
When we also have horizontal color search, our environment becomes much faster and 
clearer for any user. The color indexing GUI is in the alpha channel as <b>beta-gui.php</b> 
on Github. All you need is <b style="color: orangered;">beta-base.php</b>, the <b style="color: orangered;">/data</b> folder and the GUI will work. 
Auth is something under must have. We have not publicly removed that feature. 
That password protection function comes in the paid version. Take a look 
<b style="color: orangered;">beta-gui.php?edit=eg8mdjh7</b>, data from <b style="color: orangered;">/data</b> folder from <b style="color: orangered;">.e</b> character, 
can to be edited and it is from the orangered color, it has two parameters 
to extract i edits in the database. It almost 3 trilion combinations, and 
the data is in the /data folder, with like this <b style="color: orangered;">eg8mdjh7</b> index.
</pre><br>

<pre>
<h3>Projecting for The Future. We use simplicity.</h3>
</pre>

<pre>
I have the most powerful tool in the world, when it is updated and upgraded it will 
be an even more powerful tool for cyberspace. This is not just another software that 
offers various features. This is clearly specified open source software. With this, 
you will be able to handle the database so quickly, that you will forget about any 
other solution. As we said: <b style="color: violet;">36⁸ = 2,821,109,907,456</b>, almost 3 trillion combinations, 
to cover the whole world, every IP address, every joint in a robot, every part in a 
satellite, every node addressed by IP and so on. I can praise the software all I want, 
but until you try it, it's all for naught. The number also exceeds every IPv6 address 
because it is packed in hexadecimal notation, which is almost 20 characters less than 
my number system of 36 characters, 26 alphabets and 10 numerical values ​​from 0 to 9. 
The range is definitely huge for databases that, for example, want to have addressed 
machines outside of orbit. Let's say that each robot is full of addresses in itself 
in order to be able to communicate with the main address, noGhostSQL would solve that 
problem not by assigning it an address, but a value that is addressed from zero itself.
</pre>

<pre>
Behind this tool hides dangerous machinery with a radiation sign. With radioactive fuel 
or nitro, servers with bases like this could start working. I invite all large corporations 
to employ their developers to help on this githib repository so that the first base after 
usk.rs sees the light of day. The advantage of these databases is double indexing and direct 
work with data with only one function. You have everything on Github so see how the noSQL 
database is used.
</pre><br>

<pre>
Simple like this (Interface is on Serbian), this is <b style="color: red;">beta-gui</b> example.<br><br>
<img src="https://usk.rs/RP/DB/capture.png" alt="noGhostSQL" width="33%" style="border: 1px solid #999; border-radius: 14px; padding: 4px;">
</pre><br>

<pre>
Do you see <b style="color: red;">eg8mdjh7</b>, it is an index from the database, and there are 36⁸ = 2,821,109,907,456 
combinations in the database. That indexed data carries with it in this case a .json file with 
4 parameters, namely name, age, city and phone number. When that data is extracted from the 
database, it is displayed in the form, and when it is changed, it is entered into the database. 
The database is simple, fast and reliable. She is intuitive, easy management, creative entry, 
creative search and creative deletion :) Everything is data. Although the data in this case is 
.json, for noGhostSQL anything is data that can to be entered into the database simply. Since 
each indexed data contains one .json file, it can contain more parameters, and that is up to 
you. Edit cannot with three parameters in the .json data to edit the fourth one, to add another 
one. They can only register in the database parameters are added. And then how many parameters 
are entered in one data so much later can be edited. We can make the data editable from three 
to four parameters, but then the index loses its trillion enrollment figure.
</pre><br>


<pre>
This is the noGhostSQL <b style="color: red;">alfa-gui</b> example, You can insert any numbers parametars in .json file.<br><br>
<img src="https://usk.rs/RP/DB/capture1.png" alt="noGhostSQL" width="33%" style="border: 1px solid #999; border-radius: 14px; padding: 4px;">
</pre><br>


<pre>
BETA and ALPHA Versions are great in both cases, if someone wants a range of 4 octets for 
example and that is more than enough for them, it needs an alpha-gui environment. And the 
beta-gui environment serves for the scope of everything that exists on the planet and around 
it. Almost three trillion combinations with our character set. Along with the range of 
addresses, we also guarantee the speed of data handling. You can use alfa-gui.php and 
beta-gui.php, all you have to do is authenticate that there is a password, so that not 
everyone can manage your data. In our case, Auth is available in the paid version. You 
can use it in the local database, then just copy via ftp data to the main server. I also 
added the option of uploading the image and placing it in the _files folder, but combined 
with the indexed data. It's a lot of fun when you know you can add more hypertext.
</pre><br>


<pre>
This is the noGhostSQL <b style="color: red;">beta-gui</b> example, You can insert any numbers parametars in .json file
Now you can put image. For video we will take some more time to do.<br><br>
<img src="https://usk.rs/RP/DB/capture3.png" alt="noGhostSQL" width="33%" style="border: 1px solid #999; border-radius: 14px; padding: 4px;">
</pre><br>




</div>
