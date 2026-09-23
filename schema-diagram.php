<?php

/** Replace the "Database schema" page with a modern ER diagram:
* table cards with PK/FK badges and column types, curved relation lines with
* crow's foot notation, automatic layered layout, drag & drop, pan & zoom,
* table search, "keys only" and "related only" modes, PNG/SVG export and
* fullscreen. Colors follow the active design (light or dark). Positions are
* remembered per database in localStorage.
* @link https://github.com/pausii/adminer-schema-diagram
* @link https://www.adminer.org/plugins/#use
* @author pausii, https://github.com/pausii
* @copyright 2026 pausii
* @license https://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
* @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2 (one or other)
*/
class AdminerSchemaDiagram extends Adminer\Plugin {

	/** Emit diagram data, CSS and JS on the schema page only.
	* Returns null so other head() hooks (e.g. dark-switcher) still run.
	*/
	function head($dark = null) {
		if (!isset($_GET["schema"]) || Adminer\DB == "") {
			return null;
		}
		$data = array(
			"me" => Adminer\ME,
			"db" => Adminer\DB,
			"key" => Adminer\SERVER . "|" . Adminer\DB . "|" . (isset($_GET["ns"]) ? $_GET["ns"] : ""),
			"tables" => $this->tables(),
			"t" => array(
				"search" => $this->lang('Search table…'),
				"keys" => $this->lang('Keys only'),
				"layout" => $this->lang('Auto layout'),
				"fit" => $this->lang('Fit'),
				"full" => $this->lang('Fullscreen'),
				"zoomIn" => $this->lang('Zoom in'),
				"zoomOut" => $this->lang('Zoom out'),
				"tables" => $this->lang('tables'),
				"relations" => $this->lang('relations'),
				"hint" => $this->lang('Click a table to pin its relations (click again, click the background or press Esc to release) · "Related only" shows just the selected table and its neighbors · drag a table to move it · drag the background to pan · scroll to zoom'),
				"empty" => $this->lang('No tables.'),
				"only" => $this->lang('Related only'),
				"onlyTitle" => $this->lang('Show only the selected table and the tables it is related to (select a table first)'),
				"export" => $this->lang('Download the diagram as %s', '%s'), // keep %s for JS to fill in
			),
		);
		$json = json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
		echo "<style>" . $this->css() . "</style>\n";
		echo Adminer\script("const adminerErd = $json;\n" . $this->js());
		return null;
	}

	/** Collect tables, columns and same-database foreign keys. */
	private function tables() {
		$ns = (isset($_GET["ns"]) ? $_GET["ns"] : "");
		$allFields = Adminer\driver()->allFields();
		$return = array();
		foreach (Adminer\table_status('', true) as $name => $status) {
			if (Adminer\is_view($status)) {
				continue;
			}
			$name = (string) $name;
			$fields = array();
			$hasPrimaryInfo = false;
			foreach ((array) (isset($allFields[$name]) ? $allFields[$name] : array()) as $field) {
				$hasPrimaryInfo = $hasPrimaryInfo || array_key_exists("primary", $field);
				$fields[$field["field"]] = array(
					"n" => $field["field"],
					"t" => $field["type"] . ($field["length"] ? "($field[length])" : ""),
					"u" => (bool) $field["null"],
					"p" => !empty($field["primary"]),
				);
			}
			if (!$hasPrimaryInfo) {
				// Drivers other than MySQL don't report primary keys in allFields().
				foreach ((array) Adminer\indexes($name) as $index) {
					if ($index["type"] == "PRIMARY") {
						foreach ($index["columns"] as $column) {
							if (isset($fields[$column])) {
								$fields[$column]["p"] = true;
							}
						}
					}
				}
			}
			$keys = array();
			foreach ((array) Adminer\adminer()->foreignKeys($name) as $constraint => $fk) {
				if (!empty($fk["db"]) || (!empty($fk["ns"]) && $fk["ns"] != $ns)) {
					continue; // other database or schema, not drawable here
				}
				$keys[] = array(
					"n" => (string) $constraint,
					"t" => $fk["table"],
					"s" => array_values($fk["source"]),
					"d" => array_values($fk["target"]),
				);
			}
			$return[] = array(
				"n" => $name,
				"c" => (isset($status["Comment"]) ? (string) $status["Comment"] : ""),
				"f" => array_values($fields),
				"k" => $keys,
			);
		}
		return $return;
	}

	private function css() {
		return <<<'CSS'
#schema, #schema-link { display: none; }
.erd { --erd-fg: #222; --erd-bg: #fff; --erd-accent: #3367d6;
	--erd-card: color-mix(in srgb, var(--erd-fg) 3%, var(--erd-bg));
	--erd-canvas: color-mix(in srgb, var(--erd-fg) 5%, var(--erd-bg));
	--erd-line: color-mix(in srgb, var(--erd-fg) 14%, var(--erd-bg));
	--erd-muted: color-mix(in srgb, var(--erd-fg) 55%, var(--erd-bg));
	--erd-edge: color-mix(in srgb, var(--erd-fg) 38%, var(--erd-bg));
	--erd-pk: #d4a017; margin: .5em 0 0; color: var(--erd-fg); font-size: 13px; }
.erd * { box-sizing: border-box; }
.erd-bar { display: flex; flex-wrap: wrap; gap: 6px; align-items: center; padding: 6px; border: 1px solid var(--erd-line); border-bottom: 0; border-radius: 8px 8px 0 0; background: var(--erd-card); }
.erd-bar input { flex: 0 1 220px; min-width: 120px; padding: 5px 9px; border: 1px solid var(--erd-line); border-radius: 6px; background: var(--erd-bg); color: var(--erd-fg); font: inherit; }
.erd-bar button { padding: 5px 10px; border: 1px solid var(--erd-line); border-radius: 6px; background: var(--erd-bg); color: var(--erd-fg); font: inherit; cursor: pointer; line-height: 1.2; }
.erd-bar button:hover { border-color: var(--erd-accent); }
.erd-bar button[aria-pressed="true"] { background: var(--erd-accent); border-color: var(--erd-accent); color: var(--erd-bg); }
.erd-bar .erd-zoom { min-width: 3.6em; text-align: center; color: var(--erd-muted); font-variant-numeric: tabular-nums; }
.erd-bar .erd-stat { margin-left: auto; color: var(--erd-muted); white-space: nowrap; padding: 0 4px; }
.erd-sep { width: 1px; align-self: stretch; background: var(--erd-line); margin: 0 2px; }
.erd-view { position: relative; overflow: hidden; height: 600px; border: 1px solid var(--erd-line); border-radius: 0 0 8px 8px; cursor: grab; touch-action: none; user-select: none;
	background-color: var(--erd-canvas); background-image: radial-gradient(circle, color-mix(in srgb, var(--erd-fg) 16%, transparent) 1px, transparent 1.2px); }
.erd-view.panning { cursor: grabbing; }
.erd-world { position: absolute; left: 0; top: 0; transform-origin: 0 0; }
.erd-edges { position: absolute; left: 0; top: 0; width: 1px; height: 1px; overflow: visible; pointer-events: none; }
.erd-edges g { pointer-events: stroke; cursor: pointer; }
.erd-edges .hit { stroke: transparent; stroke-width: 12; fill: none; }
.erd-edges .ln { stroke: var(--erd-edge); stroke-width: 1.4; fill: none; transition: stroke .12s, opacity .12s; }
.erd-edges .mk { stroke: var(--erd-edge); stroke-width: 1.4; fill: var(--erd-canvas); }
.erd-edges g.hl .ln, .erd-edges g.hl .mk, .erd-edges g:hover .ln, .erd-edges g:hover .mk { stroke: var(--erd-accent); stroke-width: 2.2; }
.erd-table { position: absolute; min-width: 190px; max-width: 320px; background: var(--erd-card); border: 1px solid var(--erd-line); border-radius: 8px; overflow: hidden; cursor: move;
	box-shadow: 0 1px 2px rgba(0,0,0,.08), 0 4px 14px rgba(0,0,0,.07); transition: opacity .12s, box-shadow .12s, border-color .12s; }
.erd-table.drag { box-shadow: 0 8px 28px rgba(0,0,0,.22); z-index: 5; }
.erd-head { display: flex; align-items: center; gap: 6px; padding: 7px 10px; border-top: 3px solid var(--erd-accent);
	background: color-mix(in srgb, var(--erd-accent) 13%, var(--erd-card)); border-bottom: 1px solid var(--erd-line); }
.erd-head a { color: var(--erd-fg); font-weight: 600; text-decoration: none; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.erd-head a:hover { color: var(--erd-accent); text-decoration: underline; }
.erd-head .erd-cnt { margin-left: auto; color: var(--erd-muted); font-size: 11px; }
.erd-col { display: grid; grid-template-columns: 22px minmax(0, 1fr) auto; gap: 6px; align-items: center; padding: 2px 10px 2px 6px; min-height: 22px; line-height: 18px; }
.erd-col + .erd-col { border-top: 1px solid color-mix(in srgb, var(--erd-line) 55%, transparent); }
.erd-col .nm { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.erd-col .ty { color: var(--erd-muted); font: 11px ui-monospace, SFMono-Regular, Consolas, monospace; white-space: nowrap; max-width: 130px; overflow: hidden; text-overflow: ellipsis; }
.erd-col.pk .nm { font-weight: 600; }
.erd-col.nul .nm::after { content: "?"; color: var(--erd-muted); margin-left: 1px; }
.erd-badge { font-size: 9px; font-weight: 700; letter-spacing: .02em; text-align: center; border-radius: 3px; padding: 0 2px; line-height: 14px; }
.erd-col.pk .erd-badge { color: #fff; background: var(--erd-pk); }
.erd-col.fk:not(.pk) .erd-badge { color: var(--erd-accent); border: 1px solid var(--erd-accent); line-height: 12px; }
.erd-col.hl { background: color-mix(in srgb, var(--erd-accent) 16%, transparent); }
.erd.keys .erd-col:not(.pk):not(.fk) { display: none; }
.erd-table.off, .erd-edges g.off { display: none; }
.erd-bar button:disabled { opacity: .45; cursor: default; border-color: var(--erd-line); }
.erd.focus .erd-table:not(.hl), .erd.searching .erd-table:not(.match) { opacity: .28; }
.erd.focus .erd-edges g:not(.hl) { opacity: .12; }
.erd-table.hl, .erd-table.match { border-color: var(--erd-accent); }
.erd-table.sel { border-color: var(--erd-accent); box-shadow: 0 0 0 2px var(--erd-accent), 0 6px 22px rgba(0,0,0,.16); }
.erd-table.flash { box-shadow: 0 0 0 3px var(--erd-accent), 0 8px 28px rgba(0,0,0,.2); }
.erd-hint { margin: .4em 0 0; color: var(--erd-muted); font-size: 12px; }
.erd-empty { position: absolute; inset: 0; display: grid; place-items: center; color: var(--erd-muted); }
.erd:fullscreen { background: var(--erd-bg); padding: 10px; margin: 0; display: flex; flex-direction: column; }
.erd:fullscreen .erd-view { flex: 1; height: auto !important; }
CSS;
	}

	private function js() {
		return <<<'JS'
(() => {
const D = adminerErd, T = D.t;
const STORE = 'adminer-erd:' + D.key;
let store = {};
try { store = JSON.parse(localStorage.getItem(STORE)) || {}; } catch (e) {}
const save = () => { try { localStorage.setItem(STORE, JSON.stringify(store)); } catch (e) {} };

const view = {x: 40, y: 40, k: 1};
const tables = new Map();
const edges = [];
let root, bar, vp, world, svg, zoomLabel;
let selected = null, hovered = null;
let only = null, onlyBtn, homePos = null;

const h = (tag, cls, text) => {
	const e = document.createElement(tag);
	if (cls) e.className = cls;
	if (text != null) e.textContent = text;
	return e;
};
const S = (tag, attrs) => {
	const e = document.createElementNS('http://www.w3.org/2000/svg', tag);
	for (const k in attrs) e.setAttribute(k, attrs[k]);
	return e;
};

document.addEventListener('DOMContentLoaded', init);

function init() {
	const old = document.getElementById('schema');
	if (!old) return;
	root = h('div', 'erd');
	old.parentNode.insertBefore(root, old);
	if (store.keys) root.classList.add('keys');
	buildBar();
	vp = h('div', 'erd-view');
	world = h('div', 'erd-world');
	svg = S('svg', {'class': 'erd-edges'});
	world.append(svg);
	vp.append(world);
	root.append(vp, h('p', 'erd-hint', T.hint));
	applyTheme();
	new MutationObserver(() => requestAnimationFrame(applyTheme)).observe(document.head, {attributes: true, childList: true, subtree: true});
	matchMedia('(prefers-color-scheme: dark)').addEventListener('change', applyTheme);
	sizeView();
	addEventListener('resize', sizeView);
	document.addEventListener('fullscreenchange', () => requestAnimationFrame(sizeView));
	if (!D.tables.length) {
		vp.append(h('div', 'erd-empty', T.empty));
		return;
	}
	buildTables();
	buildEdges();
	measure();
	layout();
	const pos = store.pos || {};
	for (const t of tables.values()) {
		if (pos[t.n]) [t.x, t.y] = pos[t.n];
		place(t);
	}
	draw();
	if (store.view) {
		Object.assign(view, store.view);
		applyView();
	} else {
		fit();
	}
	bindView();
}

function buildBar() {
	bar = h('div', 'erd-bar');
	const search = h('input');
	search.type = 'search';
	search.placeholder = T.search;
	search.oninput = () => {
		const q = search.value.trim().toLowerCase();
		root.classList.toggle('searching', q != '');
		for (const t of tables.values()) t.el.classList.toggle('match', q != '' && t.n.toLowerCase().includes(q));
	};
	search.onkeydown = e => {
		if (e.key == 'Enter') {
			e.preventDefault();
			const t = [...tables.values()].find(t => t.el.classList.contains('match'));
			if (t) centerOn(t);
		} else if (e.key == 'Escape') {
			search.value = '';
			search.oninput();
		}
	};
	const btn = (label, title, fn) => {
		const b = h('button', '', label);
		b.type = 'button';
		b.title = title;
		b.onclick = fn;
		bar.append(b);
		return b;
	};
	bar.append(search, h('span', 'erd-sep'));
	const keys = btn('🔑 ' + T.keys, T.keys, () => {
		store.keys = root.classList.toggle('keys');
		keys.setAttribute('aria-pressed', store.keys);
		measure();
		if (only) {
			arrangeAround(only);
			tables.forEach(place);
		} else if (!store.pos) {
			layout();
			tables.forEach(place);
		}
		draw();
		save();
	});
	keys.setAttribute('aria-pressed', !!store.keys);
	onlyBtn = btn('◎ ' + T.only, T.onlyTitle, () => setOnly(only ? null : selected));
	onlyBtn.disabled = true;
	btn('⟲ ' + T.layout, T.layout, () => {
		setOnly(null);
		delete store.pos;
		layout();
		tables.forEach(place);
		draw();
		fit();
	});
	bar.append(h('span', 'erd-sep'));
	btn('−', T.zoomOut, () => zoomAt(1 / 1.2));
	zoomLabel = h('span', 'erd-zoom');
	bar.append(zoomLabel);
	btn('+', T.zoomIn, () => zoomAt(1.2));
	btn('⤢ ' + T.fit, T.fit, fit);
	bar.append(h('span', 'erd-sep'));
	btn('⤓ PNG', T.export.replace('%s', 'PNG'), () => exportDiagram('png'));
	btn('⤓ SVG', T.export.replace('%s', 'SVG'), () => exportDiagram('svg'));
	btn('⛶', T.full, () => document.fullscreenElement ? document.exitFullscreen() : root.requestFullscreen && root.requestFullscreen());
	let relations = 0;
	D.tables.forEach(t => relations += t.k.length);
	bar.append(h('span', 'erd-stat', D.tables.length + ' ' + T.tables + ' · ' + relations + ' ' + T.relations));
	root.append(bar);
}

function applyTheme() {
	let bg = '';
	for (let n = root; n && !bg; n = n.parentElement) {
		const c = getComputedStyle(n).backgroundColor;
		if (c && c != 'transparent' && !/^rgba\(.*,\s*0\)$/.test(c)) bg = c;
	}
	const fg = getComputedStyle(document.body).color;
	const link = document.querySelector('#content a[href], #menu a[href]');
	let accent = link ? getComputedStyle(link).color : '';
	if (!accent || accent == fg) accent = '#3b82f6';
	root.style.setProperty('--erd-bg', bg || '#fff');
	root.style.setProperty('--erd-fg', fg);
	root.style.setProperty('--erd-accent', accent);
}

function sizeView() {
	if (document.fullscreenElement) return;
	const top = vp.getBoundingClientRect().top + scrollY;
	vp.style.height = Math.max(420, innerHeight - top - 40) + 'px';
}

function buildTables() {
	for (const d of D.tables) {
		const fkCols = new Set();
		d.k.forEach(k => k.s.forEach(c => fkCols.add(c)));
		const el = h('div', 'erd-table');
		if (d.c) el.title = d.c;
		const head = h('div', 'erd-head');
		const a = h('a', '', d.n);
		a.href = D.me + 'table=' + encodeURIComponent(d.n);
		a.title = d.c ? d.n + ' — ' + d.c : d.n;
		head.append(a, h('span', 'erd-cnt', d.f.length));
		el.append(head);
		const rows = new Map();
		for (const f of d.f) {
			const fk = fkCols.has(f.n);
			const row = h('div', 'erd-col' + (f.p ? ' pk' : '') + (fk ? ' fk' : '') + (f.u ? ' nul' : ''));
			row.title = f.n + ' ' + f.t + (f.u ? ' NULL' : ' NOT NULL');
			row.append(h('span', 'erd-badge', f.p ? 'PK' : fk ? 'FK' : ''), h('span', 'nm', f.n), h('span', 'ty', f.t));
			rows.set(f.n, row);
			el.append(row);
		}
		const t = {n: d.n, d, el, head, rows, x: 0, y: 0, w: 0, h: 0, edges: []};
		tables.set(d.n, t);
		world.append(el);
		bindDrag(t, a);
		el.onmouseenter = () => { hovered = t; refocus(); };
		el.onmouseleave = () => { hovered = null; refocus(); };
	}
}

function buildEdges() {
	for (const t of tables.values()) {
		for (const k of t.d.k) {
			const p = tables.get(k.t);
			if (!p) continue;
			const f = t.d.f.find(f => f.n == k.s[0]);
			const g = S('g', {});
			const title = S('title', {});
			title.textContent = t.n + '(' + k.s.join(', ') + ') → ' + p.n + '(' + k.d.join(', ') + ')\n' + k.n;
			const hit = S('path', {'class': 'hit'});
			const ln = S('path', {'class': 'ln'});
			const mk = S('path', {'class': 'mk'});
			g.append(title, hit, ln, mk);
			svg.append(g);
			const e = {from: t, to: p, k, optional: f ? f.u : false, g, hit, ln, mk};
			g.onmouseenter = () => highlightEdge(e, true);
			g.onmouseleave = () => { highlightEdge(e, false); refocus(); };
			g.onclick = () => centerOn(p);
			edges.push(e);
			t.edges.push(e);
			if (p != t) p.edges.push(e);
		}
	}
}

function measure() {
	for (const t of tables.values()) {
		t.w = t.el.offsetWidth;
		t.h = t.el.offsetHeight;
	}
}

// Layered layout: referenced (parent) tables on the left, referencing tables to the right.
function layout() {
	const list = [...tables.values()];
	const parents = t => t.edges.filter(e => e.from == t && e.to != t).map(e => e.to);
	const children = t => t.edges.filter(e => e.to == t && e.from != t).map(e => e.from);
	const layer = new Map(), visiting = new Set();
	const depth = t => {
		if (layer.has(t)) return layer.get(t);
		visiting.add(t);
		let l = 0;
		for (const p of parents(t)) if (!visiting.has(p)) l = Math.max(l, depth(p) + 1);
		visiting.delete(t);
		layer.set(t, l);
		return l;
	};
	const linked = list.filter(t => t.edges.some(e => e.from != e.to));
	const lonely = list.filter(t => !linked.includes(t));
	linked.forEach(depth);
	// pull parents right next to their nearest child to shorten lines
	for (let i = 0; i < 3; i++) {
		for (const t of linked) {
			const ch = children(t);
			if (ch.length) layer.set(t, Math.max(layer.get(t), Math.min(...ch.map(c => layer.get(c))) - 1));
		}
	}
	const layers = [];
	for (const t of linked) (layers[layer.get(t)] = layers[layer.get(t)] || []).push(t);
	const cols = layers.filter(Boolean);
	cols.forEach(c => c.sort((a, b) => b.edges.length - a.edges.length || a.n.localeCompare(b.n)));
	// barycenter ordering sweeps
	const order = new Map();
	const index = () => cols.forEach(c => c.forEach((t, i) => order.set(t, i)));
	index();
	for (let pass = 0; pass < 6; pass++) {
		const seq = pass % 2 ? [...cols].reverse() : cols;
		for (const c of seq) {
			const bc = t => {
				const n = t.edges.map(e => e.from == t ? e.to : e.from).filter(o => o != t);
				return n.length ? n.reduce((s, o) => s + order.get(o), 0) / n.length : order.get(t);
			};
			const v = new Map(c.map(t => [t, bc(t)]));
			c.sort((a, b) => v.get(a) - v.get(b));
			c.forEach((t, i) => order.set(t, i));
		}
	}
	const GX = 110, GY = 36;
	const area = list.reduce((s, t) => s + (t.w + GX) * (t.h + GY), 0);
	const maxH = Math.max(900, Math.sqrt(area) * 0.9);
	// split tall layers into several physical columns
	const phys = [];
	for (const c of cols) {
		let cur = [], hsum = 0;
		for (const t of c) {
			if (cur.length && hsum + t.h > maxH) phys.push(cur), cur = [], hsum = 0;
			cur.push(t);
			hsum += t.h + GY;
		}
		if (cur.length) phys.push(cur);
	}
	const colH = phys.map(c => c.reduce((s, t) => s + t.h + GY, -GY));
	const tallest = Math.max(0, ...colH);
	let x = 0;
	phys.forEach((c, i) => {
		let y = (tallest - colH[i]) / 2;
		const w = Math.max(...c.map(t => t.w));
		for (const t of c) {
			t.x = x + (w - t.w) / 2;
			t.y = y;
			y += t.h + GY;
		}
		x += w + GX;
	});
	// tables without relations: shelf-packed grid below
	lonely.sort((a, b) => a.n.localeCompare(b.n));
	const width = Math.max(x - GX, 1100, Math.sqrt(lonely.reduce((s, t) => s + (t.w + 40) * (t.h + 40), 0)) * 1.6);
	let lx = 0, ly = linked.length ? tallest + 120 : 0, rowH = 0;
	for (const t of lonely) {
		if (lx && lx + t.w > width) lx = 0, ly += rowH + 40, rowH = 0;
		t.x = lx;
		t.y = ly;
		lx += t.w + 40;
		rowH = Math.max(rowH, t.h);
	}
}

function place(t) {
	t.el.style.left = t.x + 'px';
	t.el.style.top = t.y + 'px';
}

function anchorY(t, col) {
	const row = t.rows.get(col);
	if (row && row.offsetParent) return t.y + row.offsetTop + row.offsetHeight / 2;
	return t.y + t.head.offsetHeight / 2;
}

function drawEdge(e) {
	const c = e.from, p = e.to;
	const sy = anchorY(c, e.k.s[0]), ty = anchorY(p, e.k.d[0]);
	let sx, tx, so, to;
	if (c == p) {
		sx = tx = c.x + c.w; so = to = 1;
	} else if (c.x >= p.x + p.w + 30) {
		sx = c.x; so = -1; tx = p.x + p.w; to = 1;
	} else if (c.x + c.w + 30 <= p.x) {
		sx = c.x + c.w; so = 1; tx = p.x; to = -1;
	} else {
		const right = c.x + c.w / 2 >= p.x + p.w / 2;
		so = to = right ? 1 : -1;
		sx = right ? c.x + c.w : c.x;
		tx = right ? p.x + p.w : p.x;
	}
	let k1, k2;
	if (so != to) {
		k1 = k2 = Math.max(50, Math.abs(tx - sx) / 2);
	} else {
		// both ends on the same side: loop around the outermost edge
		const out = (so > 0 ? Math.max(sx, tx) : Math.min(sx, tx));
		k1 = Math.abs(out - sx) + 50;
		k2 = Math.abs(out - tx) + 50;
	}
	const d = `M${sx},${sy} C${sx + so * k1},${sy} ${tx + to * k2},${ty} ${tx},${ty}`;
	e.ln.setAttribute('d', d);
	e.hit.setAttribute('d', d);
	// crow's foot (many) at the referencing side, one / zero-or-one at the referenced side
	let m = `M${sx + so * 13},${sy} L${sx},${sy - 6} M${sx + so * 13},${sy} L${sx},${sy + 6}`;
	m += ` M${tx + to * 7},${ty - 6} L${tx + to * 7},${ty + 6}`;
	if (e.optional) m += ` M${tx + to * 18.5},${ty} a3.5,3.5 0 1,0 0.01,0`;
	else m += ` M${tx + to * 12},${ty - 6} L${tx + to * 12},${ty + 6}`;
	e.mk.setAttribute('d', m);
}

function draw(only) {
	(only ? only.edges : edges).forEach(drawEdge);
}

// Hover previews a table's relations; a click pins them until another click, a background click or Escape.
function refocus() {
	focus(hovered || selected);
}

function select(t) {
	if (!t && only) {
		// first Esc / background click leaves "related only", the next one clears the selection
		setOnly(null);
		return;
	}
	if (selected) selected.el.classList.remove('sel');
	selected = t;
	if (t) t.el.classList.add('sel');
	onlyBtn.disabled = !t;
	if (t && only && t != only) setOnly(t); // walk the graph: re-center on the clicked neighbor
	refocus();
}

const visible = t => !t.el.classList.contains('off');

function neighbors(t) {
	const set = new Set([t]);
	t.edges.forEach(e => { set.add(e.from); set.add(e.to); });
	return set;
}

// "Related only": hide everything except t and its direct neighbors and arrange them around t
// (referenced tables left, referencing tables right). Saved positions are restored on exit.
function setOnly(t) {
	if (homePos) {
		homePos.forEach((p, o) => [o.x, o.y] = p);
		homePos = null;
	}
	only = t;
	const keep = t && neighbors(t);
	for (const o of tables.values()) o.el.classList.toggle('off', !!keep && !keep.has(o));
	for (const e of edges) e.g.classList.toggle('off', !!keep && !(keep.has(e.from) && keep.has(e.to)));
	if (t) {
		homePos = new Map([...tables.values()].map(o => [o, [o.x, o.y]]));
		arrangeAround(t);
	}
	tables.forEach(place);
	draw();
	onlyBtn.setAttribute('aria-pressed', !!t);
	fit();
}

function arrangeAround(t) {
	const GX = 110, GY = 30;
	const left = [], right = [];
	for (const o of neighbors(t)) {
		if (o == t) continue;
		(t.edges.some(e => e.from == t && e.to == o) ? left : right).push(o);
	}
	t.x = 0;
	t.y = 0;
	const maxH = Math.max(t.h, 900);
	const stack = (list, dir) => {
		list.sort((a, b) => a.n.localeCompare(b.n));
		const cols = [];
		let cur = [], hsum = 0;
		for (const o of list) {
			if (cur.length && hsum + o.h > maxH) cols.push(cur), cur = [], hsum = 0;
			cur.push(o);
			hsum += o.h + GY;
		}
		if (cur.length) cols.push(cur);
		let x = (dir < 0 ? -GX : t.w + GX);
		for (const c of cols) {
			const w = Math.max(...c.map(o => o.w));
			const total = c.reduce((s, o) => s + o.h + GY, -GY);
			let y = Math.round(t.h / 2 - total / 2);
			for (const o of c) {
				o.x = Math.round((dir < 0 ? x - w : x) + (w - o.w) / 2);
				o.y = y;
				y += o.h + GY;
			}
			x += dir * (w + GX);
		}
	};
	stack(left, -1);
	stack(right, 1);
}

// Export the visible diagram as a standalone SVG (plain shapes and text, no foreignObject),
// rasterized through a canvas for PNG. Colors are read from the rendered DOM so the file
// matches the active theme.
function exportDiagram(type) {
	const list = [...tables.values()].filter(visible);
	if (!list.length) return;
	focus(null);
	const cv = document.createElement('canvas');
	cv.width = cv.height = 1;
	const cx = cv.getContext('2d', {willReadFrequently: true});
	const rgb = c => {
		cx.clearRect(0, 0, 1, 1);
		cx.fillStyle = '#000';
		cx.fillStyle = c;
		cx.fillRect(0, 0, 1, 1);
		const d = cx.getImageData(0, 0, 1, 1).data;
		return d[3] ? `rgba(${d[0]},${d[1]},${d[2]},${(d[3] / 255).toFixed(3)})` : 'none';
	};
	const cs = el => getComputedStyle(el);
	const esc = v => String(v).replace(/[&<>"]/g, c => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;'})[c]);
	const b = bounds(), pad = 30;
	const W = Math.ceil(b.x2 - b.x1 + 2 * pad), H = Math.ceil(b.y2 - b.y1 + 2 * pad);
	const canvasBg = rgb(cs(vp).backgroundColor);
	const shown = edges.filter(e => !e.g.classList.contains('off'));
	const edgeC = shown.length ? rgb(cs(shown[0].ln).stroke) : 'gray';
	let out = `<svg xmlns="http://www.w3.org/2000/svg" width="${W}" height="${H}" viewBox="${b.x1 - pad} ${b.y1 - pad} ${W} ${H}" font-family="${esc(cs(root).fontFamily)}">`;
	out += `<rect x="${b.x1 - pad}" y="${b.y1 - pad}" width="${W}" height="${H}" fill="${canvasBg}"/>`;
	for (const e of shown) {
		out += `<path d="${e.ln.getAttribute('d')}" fill="none" stroke="${edgeC}" stroke-width="1.4"/>`;
		out += `<path d="${e.mk.getAttribute('d')}" fill="${canvasBg}" stroke="${edgeC}" stroke-width="1.4"/>`;
	}
	list.forEach((t, i) => {
		const card = cs(t.el), head = cs(t.head);
		const hh = t.head.offsetHeight, x = t.x, y = t.y;
		const a = t.head.querySelector('a'), cnt = t.head.querySelector('.erd-cnt');
		out += `<clipPath id="c${i}"><rect x="${x}" y="${y}" width="${t.w}" height="${t.h}" rx="8"/></clipPath><g clip-path="url(#c${i})">`;
		out += `<rect x="${x}" y="${y}" width="${t.w}" height="${t.h}" fill="${rgb(card.backgroundColor)}"/>`;
		out += `<rect x="${x}" y="${y}" width="${t.w}" height="${hh}" fill="${rgb(head.backgroundColor)}"/>`;
		out += `<rect x="${x}" y="${y}" width="${t.w}" height="3" fill="${rgb(head.borderTopColor)}"/>`;
		out += `<rect x="${x}" y="${y + hh - 1}" width="${t.w}" height="1" fill="${rgb(head.borderBottomColor)}"/>`;
		const hy = y + 3 + (hh - 3) / 2;
		out += `<text x="${x + a.offsetLeft}" y="${hy}" dominant-baseline="central" font-size="${cs(a).fontSize}" font-weight="600" fill="${rgb(cs(a).color)}">${esc(t.n)}</text>`;
		out += `<text x="${x + t.w - 10}" y="${hy}" dominant-baseline="central" text-anchor="end" font-size="11" fill="${rgb(cs(cnt).color)}">${esc(cnt.textContent)}</text>`;
		let first = true;
		t.rows.forEach(row => {
			if (!row.offsetParent) return;
			const ry = y + row.offsetTop, cy = ry + row.offsetHeight / 2;
			if (!first) out += `<rect x="${x}" y="${ry}" width="${t.w}" height="1" fill="${rgb(cs(row).borderTopColor)}"/>`;
			first = false;
			const [badge, nm, ty] = row.children;
			if (badge.textContent) {
				const bs = cs(badge);
				const stroke = (bs.borderTopStyle == 'none' ? 'none' : rgb(bs.borderTopColor));
				out += `<rect x="${x + badge.offsetLeft + .5}" y="${cy - 7}" width="${badge.offsetWidth - 1}" height="14" rx="3" fill="${rgb(bs.backgroundColor)}" stroke="${stroke}"/>`;
				out += `<text x="${x + badge.offsetLeft + badge.offsetWidth / 2}" y="${cy}" dominant-baseline="central" text-anchor="middle" font-size="9" font-weight="700" fill="${rgb(bs.color)}">${badge.textContent}</text>`;
			}
			const ns = cs(nm), ts = cs(ty);
			out += `<text x="${x + nm.offsetLeft}" y="${cy}" dominant-baseline="central" font-size="${ns.fontSize}" font-weight="${ns.fontWeight}" fill="${rgb(ns.color)}">${esc(nm.textContent)}`
				+ (row.classList.contains('nul') ? `<tspan fill="${rgb(ts.color)}">?</tspan>` : '') + '</text>';
			out += `<text x="${x + ty.offsetLeft + ty.offsetWidth}" y="${cy}" dominant-baseline="central" text-anchor="end" font-size="${ts.fontSize}" font-family="${esc(ts.fontFamily)}" fill="${rgb(ts.color)}">${esc(ty.textContent)}</text>`;
		});
		out += `</g><rect x="${x + .5}" y="${y + .5}" width="${t.w - 1}" height="${t.h - 1}" rx="8" fill="none" stroke="${rgb(card.borderTopColor)}"/>`;
	});
	out += '</svg>';
	refocus();
	const name = 'schema-' + (D.db || 'db') + (only ? '-' + only.n : '');
	const download = (blob, file) => {
		const url = URL.createObjectURL(blob);
		const link = h('a');
		link.href = url;
		link.download = file;
		document.body.append(link);
		link.click();
		link.remove();
		setTimeout(() => URL.revokeObjectURL(url), 1000);
	};
	const blob = new Blob([out], {type: 'image/svg+xml;charset=utf-8'});
	if (type == 'svg') {
		download(blob, name + '.svg');
		return;
	}
	const img = new Image();
	img.onload = () => {
		// 2x for sharp text, capped to stay within browser canvas limits
		const k = Math.min(2, 16000 / W, 16000 / H, Math.sqrt(1.2e8 / (W * H)));
		const c = document.createElement('canvas');
		c.width = Math.round(W * k);
		c.height = Math.round(H * k);
		const g = c.getContext('2d');
		g.scale(k, k);
		g.drawImage(img, 0, 0, W, H);
		URL.revokeObjectURL(img.src);
		c.toBlob(png => download(png, name + '.png'), 'image/png');
	};
	img.src = URL.createObjectURL(blob);
}

function focus(t) {
	root.classList.toggle('focus', !!t);
	for (const o of tables.values()) {
		o.el.classList.remove('hl');
		o.rows.forEach(r => r.classList.remove('hl'));
	}
	for (const e of edges) e.g.classList.remove('hl');
	if (!t) return;
	t.el.classList.add('hl');
	for (const e of t.edges) {
		e.g.classList.add('hl');
		e.from.el.classList.add('hl');
		e.to.el.classList.add('hl');
		e.k.s.forEach(c => { const r = e.from.rows.get(c); if (r) r.classList.add('hl'); });
		e.k.d.forEach(c => { const r = e.to.rows.get(c); if (r) r.classList.add('hl'); });
		svg.append(e.g);
	}
}

function highlightEdge(e, on) {
	e.g.classList.toggle('hl', on);
	e.k.s.forEach(c => { const r = e.from.rows.get(c); if (r) r.classList.toggle('hl', on); });
	e.k.d.forEach(c => { const r = e.to.rows.get(c); if (r) r.classList.toggle('hl', on); });
}

function applyView() {
	world.style.transform = `translate(${view.x}px,${view.y}px) scale(${view.k})`;
	vp.style.backgroundSize = `${22 * view.k}px ${22 * view.k}px`;
	vp.style.backgroundPosition = `${view.x}px ${view.y}px`;
	zoomLabel.textContent = Math.round(view.k * 100) + '%';
	clearTimeout(applyView.t);
	applyView.t = setTimeout(() => { store.view = {...view}; save(); }, 300);
}

function zoomAt(f, px, py) {
	if (px == null) px = vp.clientWidth / 2, py = vp.clientHeight / 2;
	const k = Math.min(2.5, Math.max(0.1, view.k * f));
	view.x = px - (px - view.x) * k / view.k;
	view.y = py - (py - view.y) * k / view.k;
	view.k = k;
	applyView();
}

function bounds() {
	let x1 = Infinity, y1 = Infinity, x2 = -Infinity, y2 = -Infinity;
	for (const t of tables.values()) {
		if (!visible(t)) continue;
		x1 = Math.min(x1, t.x); y1 = Math.min(y1, t.y);
		x2 = Math.max(x2, t.x + t.w); y2 = Math.max(y2, t.y + t.h);
	}
	return {x1, y1, x2, y2};
}

function fit() {
	if (!tables.size) return;
	const b = bounds(), pad = 40;
	const k = Math.min(1.1, Math.max(0.1, Math.min((vp.clientWidth - 2 * pad) / (b.x2 - b.x1), (vp.clientHeight - 2 * pad) / (b.y2 - b.y1))));
	view.k = k;
	view.x = (vp.clientWidth - (b.x2 - b.x1) * k) / 2 - b.x1 * k;
	view.y = Math.max(pad, (vp.clientHeight - (b.y2 - b.y1) * k) / 2) - b.y1 * k;
	applyView();
}

function centerOn(t) {
	if (view.k < 0.7) view.k = 0.9;
	view.x = vp.clientWidth / 2 - (t.x + t.w / 2) * view.k;
	view.y = vp.clientHeight / 2 - (t.y + t.h / 2) * view.k;
	applyView();
	t.el.classList.add('flash');
	setTimeout(() => t.el.classList.remove('flash'), 900);
}

// Move/up listeners go on window (no pointer capture) so a plain click on the table name still follows the link.
function track(e, move, up) {
	const stop = () => {
		removeEventListener('pointermove', move);
		removeEventListener('pointerup', stop);
		removeEventListener('pointercancel', stop);
		up();
	};
	addEventListener('pointermove', move);
	addEventListener('pointerup', stop);
	addEventListener('pointercancel', stop);
}

function bindDrag(t, link) {
	let moved = false;
	t.el.addEventListener('pointerdown', e => {
		if (e.button) return;
		e.stopPropagation();
		const start = {mx: e.clientX, my: e.clientY, x: t.x, y: t.y};
		moved = false;
		track(e, e => {
			const dx = (e.clientX - start.mx) / view.k, dy = (e.clientY - start.my) / view.k;
			if (!moved && Math.abs(dx) + Math.abs(dy) < 4) return;
			if (!moved) {
				moved = true;
				t.el.classList.add('drag');
				world.append(t.el);
			}
			t.x = Math.round(start.x + dx);
			t.y = Math.round(start.y + dy);
			place(t);
			draw(t);
		}, () => {
			t.el.classList.remove('drag');
			if (moved && only) {
				// temporary arrangement: not saved, the table goes back home when leaving the mode
			} else if (moved) {
				store.pos = {};
				for (const o of tables.values()) store.pos[o.n] = [o.x, o.y];
				save();
			} else if (!link.contains(e.target)) {
				select(selected == t ? null : t);
			}
		});
	});
	link.addEventListener('click', e => { if (moved) e.preventDefault(); });
	link.addEventListener('dragstart', e => e.preventDefault());
}

function bindView() {
	vp.addEventListener('pointerdown', e => {
		if (e.button) return;
		const start = {mx: e.clientX, my: e.clientY, x: view.x, y: view.y};
		let moved = false;
		vp.classList.add('panning');
		track(e, e => {
			moved = moved || Math.abs(e.clientX - start.mx) + Math.abs(e.clientY - start.my) > 3;
			view.x = start.x + e.clientX - start.mx;
			view.y = start.y + e.clientY - start.my;
			applyView();
		}, () => {
			vp.classList.remove('panning');
			// a click on empty canvas (not on a relation line) clears the selection
			if (!moved && !(e.target instanceof SVGElement)) select(null);
		});
	});
	document.addEventListener('keydown', e => {
		if (e.key == 'Escape' && (selected || only) && !(e.target instanceof HTMLInputElement)) select(null);
	});
	vp.addEventListener('wheel', e => {
		e.preventDefault();
		const r = vp.getBoundingClientRect();
		zoomAt(Math.exp(-e.deltaY * (e.deltaMode ? 0.05 : 0.0015)), e.clientX - r.left, e.clientY - r.top);
	}, {passive: false});
}
})();
JS;
	}

	protected $translations = array(
		'id' => array(
			'' => 'Ganti halaman Skema database dengan diagram ER modern (drag, zoom, auto-layout, pencarian).',
			'Search table…' => 'Cari tabel…',
			'Keys only' => 'Kunci saja',
			'Auto layout' => 'Tata ulang',
			'Fit' => 'Pas layar',
			'Fullscreen' => 'Layar penuh',
			'Zoom in' => 'Perbesar',
			'Zoom out' => 'Perkecil',
			'tables' => 'tabel',
			'relations' => 'relasi',
			'Click a table to pin its relations (click again, click the background or press Esc to release) · "Related only" shows just the selected table and its neighbors · drag a table to move it · drag the background to pan · scroll to zoom' => 'Klik tabel untuk menyalakan relasinya (klik lagi, klik latar, atau tekan Esc untuk mematikan) · "Hanya terkait" menampilkan tabel terpilih beserta tetangganya saja · seret tabel untuk memindahkan · seret latar untuk menggeser · scroll untuk zoom',
			'No tables.' => 'Tidak ada tabel.',
			'Related only' => 'Hanya terkait',
			'Show only the selected table and the tables it is related to (select a table first)' => 'Tampilkan hanya tabel terpilih dan tabel-tabel yang berelasi dengannya (pilih tabel dulu)',
			'Download the diagram as %s' => 'Unduh diagram sebagai %s',
		),
	);
}
