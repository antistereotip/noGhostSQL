
<pre>This is documentation file</pre>


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

<div>
<pre>
No SQL Database - No SQL Baza, 
Name: noGhostSQL, Engine language: PHP
https://github.com/antistereotip/noGhostSQL
9 Files
1 folder - /data
Everything on repo is open source, free to use, modify and redistribute.
KISS</pre>
<div>
<pre>
The total number of possible IDs is 36 characters
≈ 1.03 × 10⁵⁶
It is a number with 57 digits.
For comparison: 8 character ID
36⁸ = 2,821,109,907,456
About 2.82 trillion combinations.
After the completed modification in the database, the result looks like this:
</pre>
</div>
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
The indexing logic is as follows: What I have created is not an index in the classical sense (as in MySQL or MongoDB),
but dividing files into folders by the first character of the ID. The folder name is actually the "address" of the document.
How it works:
Each document receives a random ID of 8 characters from the set a-z0-9, eg. k3x9m2pa. The first character of the ID determines
which folder the document goes to:
</pre>
<pre>
data/
├── a/
|   └── aulnlnbo.json   ← first character is a
├── b/
├── ...
├── g/
│   └── gxi6uggz.json   ← first character is g
├── ...
└── 9/
</pre>
</div>
<pre>
It seems to me that noGhostSQL has its own logic with color indexing.
An algorithm is trained to index by color. The ML model works on coding the  
flags themselves by color data. Indexing by colored flags has now been added.  
It will look like this in the GUI:
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
</div><br>

<div>

</div>


</div>
