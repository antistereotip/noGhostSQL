<?php

/*
 * pregled.php - javna stranica za gledaoce (samo citanje, bez prijave).
 * Podatke vuce iz api.php, pa oba fajla moraju biti u istom folderu.
 */

// ---------------------- PODESAVANJE ----------------------
$TITLE    = 'noGhostSQL';
$SUBTITLE = 'Dokumenti sačuvani kao JSON fajlovi, razvrstani u 36 foldera po prvom karakteru ID-a. Izaberi slovo ili boju da vidiš šta je u njima.';
$PER_PAGE = 12;          // koliko dokumenata se ucitava odjednom
$API      = 'api.php';
// ---------------------------------------------------------

function h($s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$config = json_encode(
    ['api' => $API, 'per' => $PER_PAGE],
    JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
);

?>
<!DOCTYPE html>
<html lang="sr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($TITLE) ?></title>
<meta name="description" content="<?= h($SUBTITLE) ?>">
<style>
    :root {
        --paper: #f0f2ee;
        --card: #fafaf7;
        --ink: #15202b;
        --muted: #566470;
        --rule: #d3d9d3;
        --tile: #e1e6e1;
        --overlay: rgba(12, 18, 24, .62);
        --serif: "Iowan Old Style", "Palatino Linotype", Palatino, "URW Palladio L", Georgia, serif;
        --sans: system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        --mono: ui-monospace, "SF Mono", Menlo, Consolas, "Liberation Mono", monospace;
    }
    @media (prefers-color-scheme: dark) {
        :root {
            --paper: #11171c;
            --card: #182026;
            --ink: #e7ece8;
            --muted: #97a4ae;
            --rule: #2b353d;
            --tile: #212b32;
            --overlay: rgba(0, 0, 0, .7);
        }
    }

    /* boja svakog reda/kartice; --on je boja teksta na punoj boji */
    .c-orangered { --c: orangered; --on: #ffffff; }
    .c-royalblue { --c: royalblue; --on: #ffffff; }
    .c-seagreen  { --c: seagreen;  --on: #ffffff; }
    .c-goldenrod { --c: goldenrod; --on: #1c1500; }
    .c-none      { --c: var(--muted); --on: #ffffff; }

    * { box-sizing: border-box; }
    [hidden] { display: none !important; }
    html { -webkit-text-size-adjust: 100%; }
    body { margin: 0; background: var(--paper); color: var(--ink); font-family: var(--sans); font-size: 16px; line-height: 1.5; }
    body.lock { overflow: hidden; }
    button, input, select { font: inherit; color: inherit; }
    button { cursor: pointer; }
    :focus-visible { outline: 2px solid var(--ink); outline-offset: 2px; }

    .wrap { max-width: 1080px; margin: 0 auto; padding: 36px 18px 64px; }

    /* ---------- naslov ---------- */
    h1 { font-family: var(--serif); font-weight: 700; font-size: clamp(2.3rem, 6.4vw, 3.7rem); line-height: 1.04; letter-spacing: -.015em; margin: 0 0 14px; }
    .lead { max-width: 58ch; margin: 0 0 6px; font-size: 1.06rem; color: var(--muted); }
    .count { margin: 0 0 26px; font-size: .95rem; color: var(--muted); min-height: 1.5em; }

    /* ---------- mapa baze: 4 reda (boje) x 9 foldera ---------- */
    .map { display: grid; gap: 8px; }
    .row { display: grid; grid-template-columns: 148px 1fr; gap: 10px; align-items: stretch; transition: opacity .15s; }
    .row.dim { opacity: .42; }
    .rowlabel { text-align: left; background: transparent; border: 1px solid var(--rule); border-left: 7px solid var(--c); border-radius: 4px; padding: 8px 10px; display: flex; flex-direction: column; justify-content: center; line-height: 1.25; }
    .rowlabel .rname { font-weight: 600; }
    .rowlabel .rcount { font-size: .82rem; color: var(--muted); }
    .rowlabel:hover { border-color: var(--c); }
    .row.on .rowlabel { background: var(--c); border-color: var(--c); color: var(--on); }
    .row.on .rowlabel .rcount { color: inherit; opacity: .85; }

    .tiles { display: grid; grid-template-columns: repeat(9, 1fr); gap: 6px; }
    .tile { position: relative; isolation: isolate; border: 1px solid var(--rule); border-radius: 4px; background: var(--tile); aspect-ratio: 5 / 4; min-width: 0; padding: 0; display: flex; align-items: center; justify-content: center; overflow: hidden; }
    .tile::before { content: ""; position: absolute; inset: 0; background: var(--c); opacity: var(--w, .1); z-index: -1; transition: opacity .15s; }
    .tile .ch { font-family: var(--mono); font-weight: 700; font-size: clamp(1rem, 2.4vw, 1.55rem); line-height: 1; }
    .tile .n { position: absolute; right: 5px; bottom: 3px; font-size: .68rem; color: var(--muted); line-height: 1; }
    .tile:hover:not(:disabled)::before { opacity: calc(var(--w, .1) + .2); }
    .tile.empty { cursor: default; }
    .tile.empty .ch { opacity: .35; }
    .tile.on { border-color: var(--c); color: var(--on); }
    .tile.on::before { opacity: 1; }
    .tile.on .n { color: inherit; }
    .map-hint { margin: 10px 0 0; font-size: .88rem; color: var(--muted); }

    /* jedan upadljiv trenutak: mapa se "popuni" pri ucitavanju */
    @keyframes tile-in { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: none; } }
    .map.intro .tile { animation: tile-in .4s both; animation-delay: calc(var(--i, 0) * 14ms); }

    /* ---------- alatke ---------- */
    .tools { display: flex; flex-wrap: wrap; gap: 10px; margin: 34px 0 10px; }
    .tools input[type=search] { flex: 1 1 240px; min-width: 0; padding: 10px 12px; background: var(--card); border: 1px solid var(--rule); border-radius: 4px; }
    .tools select { flex: 0 0 auto; padding: 10px 12px; background: var(--card); border: 1px solid var(--rule); border-radius: 4px; }
    .filters { display: flex; flex-wrap: wrap; gap: 8px; margin: 0 0 8px; }
    .fchip { background: transparent; border: 1px solid var(--ink); border-radius: 999px; padding: 3px 12px; font-size: .88rem; }
    .fchip:hover { background: var(--ink); color: var(--paper); }
    .status { margin: 0 0 14px; font-size: .9rem; color: var(--muted); min-height: 1.4em; }

    /* ---------- kartice ---------- */
    .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(290px, 1fr)); gap: 16px; align-items: start; }
    .card { display: block; color: inherit; text-decoration: none; background: var(--card); border: 1px solid var(--rule); border-left: 6px solid var(--c); border-radius: 4px; overflow: hidden; }
    .card:hover { border-color: var(--c); }
    .card .media { display: block; width: 100%; aspect-ratio: 16 / 10; object-fit: cover; background: var(--tile); border-bottom: 1px solid var(--rule); }
    .thumb { position: relative; }
    .thumb .play { position: absolute; left: 50%; top: 50%; transform: translate(-50%, -50%); width: 52px; height: 36px; border-radius: 10px; background: rgba(210, 0, 0, .92); color: #fff; display: flex; align-items: center; justify-content: center; font-size: 17px; }
    .card .body { padding: 12px 14px 14px; }
    .card .head { display: flex; justify-content: space-between; gap: 10px; align-items: baseline; margin-bottom: 8px; }
    .cid { font-family: var(--mono); font-size: .92rem; font-weight: 700; }
    .date { font-size: .8rem; color: var(--muted); white-space: nowrap; }
    .card .txt { display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; word-break: break-word; }
    .none { color: var(--muted); font-size: .92rem; margin: 0; }

    /* ---------- polja dokumenta (kartica i prozor) ---------- */
    .fields { margin: 0; }
    .f { display: grid; grid-template-columns: minmax(64px, 34%) 1fr; gap: 10px; padding: 6px 0; border-top: 1px solid var(--rule); }
    .fields > .f:first-child { border-top: 0; }
    .f dt { color: var(--muted); font-size: .84rem; padding-top: 2px; word-break: break-word; }
    .f dd { margin: 0; min-width: 0; word-break: break-word; }
    .f .fields { padding-left: 10px; border-left: 2px solid var(--rule); }
    .more-fields { padding: 6px 0 0; font-size: .84rem; color: var(--muted); border-top: 1px solid var(--rule); }
    .nil { color: var(--muted); }
    .num { font-variant-numeric: tabular-nums; }
    .pill { display: inline-block; padding: 0 9px; border-radius: 999px; font-size: .84rem; border: 1px solid var(--rule); }
    .pill.yes { border-color: var(--ink); }
    .chips { display: flex; flex-wrap: wrap; gap: 5px; }
    .chip { padding: 0 9px; border: 1px solid var(--rule); border-radius: 999px; font-size: .88rem; }
    .subitem + .subitem { margin-top: 6px; }
    .ext { color: inherit; text-decoration-thickness: 1px; text-underline-offset: 3px; }

    /* ---------- stanja ---------- */
    .state { grid-column: 1 / -1; padding: 40px 10px; text-align: center; color: var(--muted); }
    .state p { margin: 0 0 14px; }
    .state .detail { font-family: var(--mono); font-size: .84rem; max-width: 62ch; margin: 0 auto 16px; padding: 8px 12px; border: 1px solid var(--rule); border-radius: 4px; background: var(--card); color: var(--ink); word-break: break-word; }
    .btn { background: transparent; border: 1px solid var(--ink); border-radius: 4px; padding: 8px 16px; }
    .btn:hover:not(:disabled) { background: var(--ink); color: var(--paper); }
    .btn:disabled { opacity: .4; cursor: default; }
    .more { text-align: center; margin-top: 26px; }
    .foot { margin-top: 46px; font-size: .84rem; color: var(--muted); }

    /* ---------- prozor sa detaljima ---------- */
    .overlay { position: fixed; inset: 0; background: var(--overlay); display: flex; align-items: flex-start; justify-content: center; padding: 4vh 14px; overflow-y: auto; z-index: 20; animation: fade .15s; }
    .overlay[hidden] { display: none; }
    @keyframes fade { from { opacity: 0; } to { opacity: 1; } }
    .panel { width: 100%; max-width: 760px; background: var(--card); border: 1px solid var(--rule); border-top: 7px solid var(--c); border-radius: 4px; }
    .phead { display: flex; flex-wrap: wrap; gap: 10px 14px; align-items: center; padding: 14px 18px; border-bottom: 1px solid var(--rule); position: sticky; top: 0; background: var(--card); z-index: 1; }
    .phead .pid { font-family: var(--mono); font-size: 1.15rem; font-weight: 700; }
    .phead .pdate { color: var(--muted); font-size: .88rem; flex: 1 1 auto; }
    .phead .btn { padding: 5px 12px; font-size: .9rem; }
    .pbody { padding: 16px 18px 6px; }
    .pbody .mimg { display: block; max-width: 100%; max-height: 62vh; margin: 0 auto 16px; border-radius: 4px; background: var(--tile); }
    .vid { margin: 0 0 16px; max-width: 100%; }
    .vcap { font-size: .84rem; color: var(--muted); margin-bottom: 4px; }
    .yt { position: relative; display: block; aspect-ratio: 16 / 9; background: #000; border-radius: 4px; overflow: hidden; }
    .yt img { width: 100%; height: 100%; object-fit: cover; display: block; }
    .yt .play { position: absolute; left: 50%; top: 50%; transform: translate(-50%, -50%); width: 64px; height: 44px; border-radius: 12px; background: rgba(210, 0, 0, .92); color: #fff; display: flex; align-items: center; justify-content: center; font-size: 22px; }
    .yt:hover .play { background: #f00; }
    .yt iframe { position: absolute; inset: 0; width: 100%; height: 100%; border: 0; }
    .pfoot { padding: 10px 18px 18px; }

    /* ---------- mali ekrani ---------- */
    @media (max-width: 640px) {
        .wrap { padding-top: 26px; }
        .row { grid-template-columns: 1fr; gap: 6px; }
        .rowlabel { flex-direction: row; justify-content: space-between; align-items: baseline; }
        .tiles { gap: 4px; }
        .tile { aspect-ratio: auto; height: 42px; }
        .tile .n { display: none; }
        .f { grid-template-columns: 1fr; gap: 0; }
    }

    @media (prefers-reduced-motion: reduce) {
        *, *::before, *::after { animation: none !important; transition: none !important; }
    }
</style>
</head>
<body>
<div class="wrap">

    <header>
        <h1><?= h($TITLE) ?></h1>
        <p class="lead"><?= h($SUBTITLE) ?></p>
        <p class="count" id="count">Učitavam bazu…</p>
    </header>

    <!-- mapa baze: svaki red je jedna boja, svako polje jedan folder -->
    <section aria-label="Mapa baze po folderima i bojama">
        <div class="map" id="map"></div>
        <p class="map-hint">Svaki red je jedna boja. Klikni slovo da vidiš dokumente čiji ID počinje njime, ili ime boje da vidiš ceo red.</p>
    </section>

    <section class="tools" aria-label="Pretraga">
        <input type="search" id="q" placeholder="Traži po tekstu ili ID-u" aria-label="Traži po tekstu ili ID-u" autocomplete="off">
        <select id="sort" aria-label="Redosled">
            <option value="new">Najnoviji prvo</option>
            <option value="old">Najstariji prvo</option>
            <option value="random">Nasumično</option>
        </select>
    </section>

    <div class="filters" id="filters" hidden></div>
    <p class="status" id="status" aria-live="polite"></p>

    <main>
        <div class="grid" id="grid" aria-busy="true"></div>
        <div class="more"><button type="button" class="btn" id="more" hidden>Prikaži još</button></div>
    </main>

    <p class="foot">Podaci se čitaju preko <?= h($API) ?> i samo se prikazuju, ovde se ništa ne može menjati.</p>

    <noscript><p class="state">Za prikaz baze potreban je uključen JavaScript.</p></noscript>
</div>

<!-- prozor sa svim podacima jednog dokumenta -->
<div class="overlay" id="modal" hidden>
    <div class="panel" id="panel" role="dialog" aria-modal="true" aria-labelledby="mid">
        <div class="phead">
            <span class="pid" id="mid"></span>
            <span class="pdate" id="mdate"></span>
            <button type="button" class="btn" id="mprev">Prethodni</button>
            <button type="button" class="btn" id="mnext">Sledeći</button>
            <button type="button" class="btn" id="mclose">Zatvori</button>
        </div>
        <div class="pbody" id="mbody"></div>
        <div class="pfoot"><button type="button" class="btn" id="mcopy">Kopiraj link</button></div>
    </div>
</div>

<script>window.PREGLED = <?= $config ?>;</script>
<script>
(function () {
    'use strict';

    var CFG = window.PREGLED || {};
    var API = CFG.api || 'api.php';
    var PER = CFG.per || 12;
    var COLOR_CLASS = { orangered: 'c-orangered', royalblue: 'c-royalblue', seagreen: 'c-seagreen', goldenrod: 'c-goldenrod' };
    var YT_ID = /^[A-Za-z0-9_-]{11}$/;

    var $ = function (id) { return document.getElementById(id); };
    var els = {
        count: $('count'), map: $('map'), search: $('q'), sort: $('sort'), filters: $('filters'),
        status: $('status'), grid: $('grid'), more: $('more'),
        modal: $('modal'), panel: $('panel'), mId: $('mid'), mDate: $('mdate'), mBody: $('mbody'),
        mPrev: $('mprev'), mNext: $('mnext'), mClose: $('mclose'), mCopy: $('mcopy')
    };

    var state = {
        q: '', color: '', bucket: '', sort: 'new', seed: Math.random().toString(36).slice(2, 10),
        page: 0, pages: 1, found: 0, items: [], stats: null,
        loading: false, req: 0, pushed: false, current: null, opener: null
    };

    /* ------------------------------------------------------------ pomocne */

    function el(tag, cls, text) {
        var n = document.createElement(tag);
        if (cls) n.className = cls;
        if (text !== undefined && text !== null) n.textContent = text;
        return n;
    }

    function cc(color) { return COLOR_CLASS[color] || 'c-none'; }

    function plural(n, one, few, many) {
        var m10 = n % 10, m100 = n % 100;
        if (m10 === 1 && m100 !== 11) return one;
        if (m10 >= 2 && m10 <= 4 && (m100 < 12 || m100 > 14)) return few;
        return many;
    }

    function docs(n) { return n + ' ' + plural(n, 'dokument', 'dokumenta', 'dokumenata'); }

    function labelOf(k) {
        var s = String(k).replace(/[_-]+/g, ' ').trim();
        return s ? s.charAt(0).toUpperCase() + s.slice(1) : String(k);
    }

    function fmtDate(s, withTime) {
        var m = /^(\d{4})-(\d{2})-(\d{2})(?: (\d{2}):(\d{2}))?/.exec(s || '');
        if (!m) return s || '';
        var d = parseInt(m[3], 10) + '.' + parseInt(m[2], 10) + '.' + m[1] + '.';
        return withTime && m[4] ? d + ' ' + m[4] + ':' + m[5] : d;
    }

    function isHttpUrl(s) { return /^https?:\/\/[^\s]+$/i.test(s); }
    function imgUrl(id) { return API + '?action=image&id=' + encodeURIComponent(id); }
    function ytThumb(id) { return 'https://i.ytimg.com/vi/' + id + '/hqdefault.jpg'; }

    function fetchJson(params) {
        var qs = new URLSearchParams();
        Object.keys(params).forEach(function (k) {
            if (params[k] !== '' && params[k] !== null && params[k] !== undefined) qs.set(k, params[k]);
        });
        return fetch(API + '?' + qs.toString(), { headers: { Accept: 'application/json' } }).then(function (r) {
            return r.text().then(function (text) {
                var d = null;
                try { d = JSON.parse(text); } catch (e) { /* nije JSON */ }

                if (d === null && r.status === 404) {
                    throw new Error(API + ' nije pronađen (HTTP 404). Ubaci ' + API + ' u isti folder kao ova stranica.');
                }

                if (d === null) {
                    var why = text.trim()
                        ? 'odgovor nije JSON: ' + text.trim().replace(/\s+/g, ' ').slice(0, 160)
                        : 'prazan odgovor (verovatno greska u PHP-u)';
                    throw new Error(API + ' (HTTP ' + r.status + '): ' + why);
                }

                if (!r.ok) throw new Error(d.error ? d.error : API + ' (HTTP ' + r.status + ')');
                return d;
            });
        }, function () {
            throw new Error('Server nije dostupan ili ' + API + ' ne postoji na ovoj adresi.');
        });
    }

    function videoPaths(videos) {
        var skip = {};
        (videos || []).forEach(function (v) { skip[v.path] = true; });
        return skip;
    }

    /* ------------------------------------------------------ prikaz polja */

    function renderValue(v, full, path, skip) {
        if (v === null || v === undefined) return el('span', 'nil', '—');
        if (typeof v === 'boolean') return el('span', 'pill ' + (v ? 'yes' : 'no'), v ? 'Da' : 'Ne');
        if (typeof v === 'number') return el('span', 'num', String(v));

        if (typeof v === 'string') {
            if (full && isHttpUrl(v)) {
                var a = el('a', 'ext', v);
                a.href = v;
                a.target = '_blank';
                a.rel = 'noopener noreferrer';
                return a;
            }
            return el('span', 'txt', v);
        }

        if (Array.isArray(v)) {
            if (!v.length) return el('span', 'nil', '—');

            var flat = v.every(function (x) { return x === null || typeof x !== 'object'; });

            if (flat) {
                var chips = el('span', 'chips');
                var n = 0;
                v.forEach(function (x, i) {
                    if (skip && skip[path + '.' + i]) return;
                    chips.appendChild(el('span', 'chip', x === null ? '—' : String(x)));
                    n++;
                });
                return n ? chips : null;
            }

            var list = el('div', 'sublist');
            v.forEach(function (x, i) {
                var node = renderValue(x, full, path + '.' + i, skip);
                if (!node) return;
                var item = el('div', 'subitem');
                item.appendChild(node);
                list.appendChild(item);
            });
            return list;
        }

        return renderFields(v, { full: full, skip: skip }, path);
    }

    function renderFields(obj, opt, prefix) {
        var dl = el('dl', 'fields');
        var shown = 0, hidden = 0;

        Object.keys(obj).forEach(function (k) {
            var path = prefix ? prefix + '.' + k : k;
            if (opt.skip && opt.skip[path]) return;

            if (opt.limit && shown >= opt.limit) { hidden++; return; }

            var val = renderValue(obj[k], opt.full, path, opt.skip);
            if (!val) return;

            var row = el('div', 'f');
            row.appendChild(el('dt', null, labelOf(k)));
            var dd = el('dd');
            dd.appendChild(val);
            row.appendChild(dd);
            dl.appendChild(row);
            shown++;
        });

        if (hidden) dl.appendChild(el('div', 'more-fields', '+ još ' + hidden + ' ' + plural(hidden, 'polje', 'polja', 'polja')));
        return dl;
    }

    /* ------------------------------------------------------------ kartica */

    function renderCard(it) {
        var a = el('a', 'card ' + cc(it.color));
        a.href = '#' + it.id;
        a.dataset.id = it.id;

        var firstVideo = (it.videos || []).filter(function (v) { return YT_ID.test(v.id); })[0];

        if (it.image) {
            var img = el('img', 'media');
            img.src = imgUrl(it.id);
            img.alt = '';
            img.loading = 'lazy';
            img.addEventListener('error', function () { img.remove(); });
            a.appendChild(img);
        } else if (firstVideo) {
            var th = el('div', 'thumb');
            var ti = el('img', 'media');
            ti.src = ytThumb(firstVideo.id);
            ti.alt = '';
            ti.loading = 'lazy';
            ti.addEventListener('error', function () { ti.style.visibility = 'hidden'; });
            th.appendChild(ti);
            th.appendChild(el('span', 'play', '\u25B6'));
            a.appendChild(th);
        }

        var body = el('div', 'body');
        var head = el('div', 'head');
        head.appendChild(el('span', 'cid', it.id));
        head.appendChild(el('span', 'date', fmtDate(it.created)));
        body.appendChild(head);

        var fields = renderFields(it.data || {}, { full: false, limit: 6, skip: videoPaths(it.videos) }, '');
        body.appendChild(fields.childNodes.length ? fields : el('p', 'none', 'Dokument nema tekstualnih polja.'));

        a.appendChild(body);
        return a;
    }

    /* ---------------------------------------------------------- mapa baze */

    function activeColor() {
        if (state.color) return state.color;
        if (state.bucket && state.stats) {
            var hit = state.stats.colors.filter(function (c) { return c.chars.indexOf(state.bucket) !== -1; })[0];
            return hit ? hit.name : '';
        }
        return '';
    }

    function syncMapState() {
        var act = activeColor();

        Array.prototype.forEach.call(els.map.querySelectorAll('.row'), function (row) {
            var on = state.color === row.dataset.color;
            row.classList.toggle('on', on);
            row.classList.toggle('dim', !!act && act !== row.dataset.color);
            row.querySelector('.rowlabel').setAttribute('aria-pressed', on ? 'true' : 'false');
        });

        Array.prototype.forEach.call(els.map.querySelectorAll('.tile'), function (t) {
            var on = state.bucket === t.dataset.ch;
            t.classList.toggle('on', on);
            t.setAttribute('aria-pressed', on ? 'true' : 'false');
        });
    }

    function renderMap() {
        var st = state.stats;
        els.map.textContent = '';

        var max = 1;
        st.colors.forEach(function (c) {
            c.chars.forEach(function (ch) { max = Math.max(max, st.buckets[ch] || 0); });
        });

        var idx = 0;

        st.colors.forEach(function (c) {
            var row = el('div', 'row ' + cc(c.name));
            row.dataset.color = c.name;

            var lab = el('button', 'rowlabel');
            lab.type = 'button';
            lab.appendChild(el('span', 'rname', c.name));
            lab.appendChild(el('span', 'rcount', docs(c.count)));
            lab.addEventListener('click', function () { toggleColor(c.name); });
            row.appendChild(lab);

            var tiles = el('div', 'tiles');

            c.chars.forEach(function (ch) {
                var n = st.buckets[ch] || 0;
                var t = el('button', 'tile' + (n ? '' : ' empty'));
                t.type = 'button';
                t.dataset.ch = ch;
                t.disabled = n === 0;
                t.style.setProperty('--w', n ? (0.14 + 0.46 * n / max).toFixed(2) : '0.05');
                t.style.setProperty('--i', String(idx++));
                t.setAttribute('aria-label', 'Folder ' + ch + ', ' + docs(n) + ', boja ' + c.name);
                t.appendChild(el('span', 'ch', ch));
                t.appendChild(el('span', 'n', String(n)));
                t.addEventListener('click', function () { toggleBucket(ch); });
                tiles.appendChild(t);
            });

            row.appendChild(tiles);
            els.map.appendChild(row);
        });

        els.map.classList.add('intro');
        els.count.textContent = docs(st.total) + ' u 36 foldera, grupisanih u ' + st.colors.length + ' boje.';
        syncMapState();
    }

    /* ------------------------------------------------------------ filteri */

    function syncUrl() {
        var qs = new URLSearchParams();
        if (state.q) qs.set('q', state.q);
        if (state.bucket) qs.set('folder', state.bucket);
        if (state.color) qs.set('boja', state.color);
        if (state.sort !== 'new') qs.set('redosled', state.sort);
        var s = qs.toString();
        history.replaceState(history.state, '', location.pathname + (s ? '?' + s : '') + location.hash);
    }

    function readUrl() {
        var qs = new URLSearchParams(location.search);
        state.q = (qs.get('q') || '').trim();
        var b = qs.get('folder') || '';
        state.bucket = /^[a-z0-9]$/.test(b) ? b : '';
        var c = qs.get('boja') || '';
        state.color = COLOR_CLASS[c] && !state.bucket ? c : '';
        var s = qs.get('redosled') || '';
        state.sort = s === 'old' || s === 'random' ? s : 'new';
    }

    function renderFilters() {
        els.filters.textContent = '';
        var any = false;

        function chip(text, clear) {
            any = true;
            var b = el('button', 'fchip', text + ' ×');
            b.type = 'button';
            b.setAttribute('aria-label', 'Ukloni filter: ' + text);
            b.addEventListener('click', function () { clear(); changed(); });
            els.filters.appendChild(b);
        }

        if (state.q) chip('Tekst: ' + state.q, function () { state.q = ''; els.search.value = ''; });
        if (state.color) chip('Boja: ' + state.color, function () { state.color = ''; });
        if (state.bucket) chip('Folder: ' + state.bucket, function () { state.bucket = ''; });

        els.filters.hidden = !any;
    }

    function changed() {
        syncUrl();
        syncMapState();
        renderFilters();
        load(true);
    }

    function toggleColor(name) {
        state.color = state.color === name ? '' : name;
        state.bucket = '';
        changed();
    }

    function toggleBucket(ch) {
        state.bucket = state.bucket === ch ? '' : ch;
        state.color = '';
        changed();
    }

    /* ----------------------------------------------------------- ucitavanje */

    function showState(msg, btnText, onClick, detail) {
        els.grid.textContent = '';
        var box = el('div', 'state');
        box.appendChild(el('p', null, msg));
        if (detail) box.appendChild(el('p', 'detail', detail));
        if (btnText) {
            var b = el('button', 'btn', btnText);
            b.type = 'button';
            b.addEventListener('click', onClick);
            box.appendChild(b);
        }
        els.grid.appendChild(box);
    }

    function clearAll() {
        state.q = ''; state.color = ''; state.bucket = '';
        els.search.value = '';
        changed();
    }

    function load(reset) {
        if (state.loading && !reset) return Promise.resolve();

        var my = ++state.req;
        state.loading = true;

        if (reset) {
            state.page = 0;
            state.items = [];
            els.grid.setAttribute('aria-busy', 'true');
            els.more.hidden = true;
        }

        var params = {
            action: 'list', page: state.page + 1, per: PER,
            q: state.q, color: state.color, bucket: state.bucket,
            sort: state.sort, seed: state.seed
        };
        if (!state.stats) params.stats = '1';

        return fetchJson(params).then(function (d) {
            if (my !== state.req) return;

            if (d.stats) { state.stats = d.stats; renderMap(); }

            state.page = d.page;
            state.pages = d.pages;
            state.found = d.found;

            if (reset) els.grid.textContent = '';

            d.items.forEach(function (it) {
                state.items.push(it);
                els.grid.appendChild(renderCard(it));
            });

            if (!state.items.length) {
                var filtered = state.q || state.color || state.bucket;
                showState(
                    filtered ? 'Nema dokumenata za ovaj izbor.' : 'U bazi još nema dokumenata.',
                    filtered ? 'Očisti filtere' : '', clearAll
                );
                els.status.textContent = '';
            } else {
                els.status.textContent = 'Prikazano ' + state.items.length + ' od ' + state.found;
            }

            els.more.hidden = state.page >= state.pages;
            els.grid.setAttribute('aria-busy', 'false');
        }).catch(function (err) {
            if (my !== state.req) return;
            showState('Podaci se nisu učitali.', 'Pokušaj ponovo', function () { load(true); }, err && err.message ? err.message : '');
            if (!state.stats) els.count.textContent = 'Baza trenutno nije dostupna.';
            els.status.textContent = '';
            els.grid.setAttribute('aria-busy', 'false');
        }).then(function () {
            if (my === state.req) state.loading = false;
        });
    }

    /* ------------------------------------------------------- prozor (modal) */

    function renderVideo(v) {
        if (!YT_ID.test(v.id)) return null;

        var wrap = el('div', 'vid');
        wrap.appendChild(el('div', 'vcap', labelOf(String(v.path).split('.')[0])));

        var a = el('a', 'yt');
        a.href = 'https://www.youtube.com/watch?v=' + v.id + (v.start ? '&t=' + v.start + 's' : '');
        a.target = '_blank';
        a.rel = 'noopener noreferrer';
        a.setAttribute('aria-label', 'Pusti video');

        var img = el('img');
        img.src = ytThumb(v.id);
        img.alt = '';
        img.loading = 'lazy';
        img.addEventListener('error', function () { img.style.visibility = 'hidden'; });
        a.appendChild(img);
        a.appendChild(el('span', 'play', '\u25B6'));

        a.addEventListener('click', function (ev) {
            ev.preventDefault();
            var f = document.createElement('iframe');
            f.src = 'https://www.youtube-nocookie.com/embed/' + v.id + '?autoplay=1&rel=0' + (v.start > 0 ? '&start=' + v.start : '');
            f.title = 'YouTube video';
            f.allow = 'accelerometer; autoplay; encrypted-media; picture-in-picture; fullscreen';
            f.allowFullscreen = true;
            f.referrerPolicy = 'strict-origin-when-cross-origin';
            a.textContent = '';
            a.removeAttribute('href');
            a.appendChild(f);
        });

        wrap.appendChild(a);
        return wrap;
    }

    function indexOfCurrent() {
        for (var i = 0; i < state.items.length; i++) {
            if (state.items[i].id === state.current) return i;
        }
        return -1;
    }

    function showModal(it) {
        state.current = it.id;
        els.panel.className = 'panel ' + cc(it.color);
        els.mId.textContent = it.id;
        els.mDate.textContent = fmtDate(it.created, true);
        els.mBody.textContent = '';

        if (it.image) {
            var img = el('img', 'mimg');
            img.src = imgUrl(it.id);
            img.alt = 'Slika dokumenta ' + it.id;
            img.addEventListener('error', function () { img.remove(); });
            els.mBody.appendChild(img);
        }

        (it.videos || []).forEach(function (v) {
            var node = renderVideo(v);
            if (node) els.mBody.appendChild(node);
        });

        var fields = renderFields(it.data || {}, { full: true, skip: videoPaths(it.videos) }, '');
        if (fields.childNodes.length) {
            els.mBody.appendChild(fields);
        } else if (!els.mBody.childNodes.length) {
            els.mBody.appendChild(el('p', 'none', 'Dokument nema polja.'));
        }

        var i = indexOfCurrent();
        els.mPrev.disabled = i <= 0;
        els.mNext.disabled = i < 0 || (i >= state.items.length - 1 && state.page >= state.pages);
        els.mCopy.textContent = 'Kopiraj link';

        if (els.modal.hidden) {
            state.opener = document.activeElement;
            els.modal.hidden = false;
            document.body.classList.add('lock');
        }
        els.modal.scrollTop = 0;
        els.mClose.focus();
    }

    function showModalError(id) {
        state.current = null;
        els.panel.className = 'panel c-none';
        els.mId.textContent = id;
        els.mDate.textContent = '';
        els.mBody.textContent = '';
        els.mBody.appendChild(el('p', 'none', 'Dokument ne postoji ili nije javan.'));
        els.mPrev.disabled = true;
        els.mNext.disabled = true;

        if (els.modal.hidden) {
            state.opener = document.activeElement;
            els.modal.hidden = false;
            document.body.classList.add('lock');
        }
        els.mClose.focus();
    }

    function hideModal() {
        if (els.modal.hidden) return;
        els.modal.hidden = true;
        els.mBody.textContent = '';   // zaustavlja video
        document.body.classList.remove('lock');
        state.current = null;
        if (state.opener && state.opener.focus) state.opener.focus();
        state.opener = null;
    }

    function openById(id) {
        for (var i = 0; i < state.items.length; i++) {
            if (state.items[i].id === id) { showModal(state.items[i]); return; }
        }
        fetchJson({ action: 'get', id: id }).then(showModal).catch(function () { showModalError(id); });
    }

    function route() {
        var m = /^#([a-z0-9]{8})$/.exec(location.hash);
        if (m) openById(m[1]); else hideModal();
    }

    function go(id) {
        history.replaceState(history.state, '', location.pathname + location.search + '#' + id);
        openById(id);
    }

    function step(dir) {
        var i = indexOfCurrent();
        if (i < 0) return;
        var j = i + dir;

        if (j >= 0 && j < state.items.length) { go(state.items[j].id); return; }

        if (dir > 0 && state.page < state.pages) {
            load(false).then(function () {
                if (i + 1 < state.items.length) go(state.items[i + 1].id);
            });
        }
    }

    function closeModal() {
        if (els.modal.hidden) return;

        if (state.pushed) {
            state.pushed = false;
            history.back();               // hashchange zatvara prozor
        } else {
            history.replaceState(history.state, '', location.pathname + location.search);
            hideModal();
        }
    }

    function copyLink() {
        var url = location.href;
        var done = function () {
            els.mCopy.textContent = 'Link kopiran';
            setTimeout(function () { els.mCopy.textContent = 'Kopiraj link'; }, 1600);
        };

        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(url).then(done);
            return;
        }

        var ta = document.createElement('textarea');
        ta.value = url;
        ta.style.position = 'fixed';
        ta.style.opacity = '0';
        document.body.appendChild(ta);
        ta.select();
        try { document.execCommand('copy'); done(); } catch (e) { /* nista */ }
        document.body.removeChild(ta);
    }

    /* -------------------------------------------------------------- dogadjaji */

    els.grid.addEventListener('click', function (ev) {
        if (ev.target.closest && ev.target.closest('a.card')) state.pushed = true;
    });

    els.more.addEventListener('click', function () { load(false); });
    els.mClose.addEventListener('click', closeModal);
    els.mPrev.addEventListener('click', function () { step(-1); });
    els.mNext.addEventListener('click', function () { step(1); });
    els.mCopy.addEventListener('click', copyLink);

    els.modal.addEventListener('click', function (ev) { if (ev.target === els.modal) closeModal(); });

    document.addEventListener('keydown', function (ev) {
        if (els.modal.hidden) return;

        if (ev.key === 'Escape') { closeModal(); return; }
        if (ev.key === 'ArrowLeft') { step(-1); return; }
        if (ev.key === 'ArrowRight') { step(1); return; }

        if (ev.key === 'Tab') {
            var f = els.modal.querySelectorAll('button:not([disabled]), a[href]');
            if (!f.length) return;
            var first = f[0], last = f[f.length - 1];

            if (ev.shiftKey && document.activeElement === first) { ev.preventDefault(); last.focus(); }
            else if (!ev.shiftKey && document.activeElement === last) { ev.preventDefault(); first.focus(); }
        }
    });

    window.addEventListener('hashchange', route);

    var timer = null;
    els.search.addEventListener('input', function () {
        clearTimeout(timer);
        timer = setTimeout(function () {
            var v = els.search.value.trim();
            if (v === state.q) return;
            state.q = v;
            changed();
        }, 250);
    });

    els.sort.addEventListener('change', function () {
        state.sort = els.sort.value;
        if (state.sort === 'random') state.seed = Math.random().toString(36).slice(2, 10);
        changed();
    });

    /* ----------------------------------------------------------------- start */

    readUrl();
    els.search.value = state.q;
    els.sort.value = state.sort;
    renderFilters();
    load(true).then(route);
})();
</script>
</body>
</html>
