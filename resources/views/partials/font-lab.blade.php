{{--
    Font lab: try other typefaces on the real pages. Local only (the layout
    includes it when APP_ENV is local), never in production.

    It swaps the --font-display and --font-body variables on <html>, so every
    heading and paragraph on the page follows, and loads each font from Bunny
    Fonts (the GDPR-friendly Google Fonts mirror the site already uses). The
    choice is kept in localStorage, so it follows you from page to page.
--}}
<div id="font-lab" class="font-lab" data-open="false">
    <button type="button" class="font-lab-toggle" aria-expanded="false" aria-controls="font-lab-panel">Aa Fonts</button>
    <div id="font-lab-panel" class="font-lab-panel" hidden>
        <div class="font-lab-head">
            <strong>Font lab</strong>
            <span>Local only</span>
        </div>

        <label class="font-lab-label" for="font-lab-pairing">Pairing</label>
        <select id="font-lab-pairing" class="font-lab-select"></select>

        <label class="font-lab-label" for="font-lab-display">Headings</label>
        <select id="font-lab-display" class="font-lab-select"></select>

        <label class="font-lab-label" for="font-lab-body">Body text</label>
        <select id="font-lab-body" class="font-lab-select"></select>

        <label class="font-lab-label" for="font-lab-size">Heading size <output id="font-lab-size-out">100%</output></label>
        <input id="font-lab-size" type="range" min="70" max="115" step="5" value="100">

        <div class="font-lab-actions">
            <button type="button" id="font-lab-prev">&larr; Prev</button>
            <button type="button" id="font-lab-next">Next &rarr;</button>
            <button type="button" id="font-lab-reset">Reset</button>
        </div>
        <p class="font-lab-note">Arrow keys cycle pairings while the panel is open.</p>
    </div>
</div>

<style>
    .font-lab { position: fixed; left: 16px; bottom: 16px; z-index: 9999; font: 13px/1.4 -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; color: #151513; }
    .font-lab-toggle { cursor: pointer; border: 0; border-radius: 999px; background: #151513; color: #FAF8F3; padding: 9px 15px; font: 600 13px -apple-system, sans-serif; box-shadow: 0 4px 16px rgb(0 0 0 / 0.2); }
    .font-lab-panel { margin-top: 8px; width: 270px; padding: 14px; border-radius: 12px; background: #fff; border: 1px solid #DAD5CA; box-shadow: 0 12px 32px rgb(0 0 0 / 0.18); }
    .font-lab-panel[hidden] { display: none; }
    .font-lab-head { display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 10px; }
    .font-lab-head span { color: #625F58; font-size: 11px; }
    .font-lab-label { display: flex; justify-content: space-between; margin: 10px 0 4px; font-weight: 600; font-size: 12px; }
    .font-lab-select { width: 100%; padding: 6px 8px; border: 1px solid #857F74; border-radius: 6px; background: #fff; font: 13px -apple-system, sans-serif; }
    .font-lab input[type=range] { width: 100%; }
    .font-lab-actions { display: flex; gap: 6px; margin-top: 12px; }
    .font-lab-actions button { flex: 1; cursor: pointer; padding: 6px 0; border: 1px solid #DAD5CA; border-radius: 6px; background: #FAF8F3; font: 600 12px -apple-system, sans-serif; }
    .font-lab-note { margin: 10px 0 0; color: #625F58; font-size: 11px; }
    html[data-font-lab-scale] :is(h1, h2, h3, .font-display) { font-size-adjust: var(--font-lab-adjust); }
</style>

<script>
(() => {
    // Display fonts for headings, then body fonts. Each: [name, Bunny family slug, weights, fallback].
    const display = [
        ['Instrument Serif', 'instrument-serif', '400,400i', 'serif'],
        ['Fraunces', 'fraunces', '300,400,500,400i', 'serif'],
        ['Newsreader', 'newsreader', '400,500,400i', 'serif'],
        ['Playfair Display', 'playfair-display', '400,500,400i', 'serif'],
        ['DM Serif Display', 'dm-serif-display', '400,400i', 'serif'],
        ['Young Serif', 'young-serif', '400', 'serif'],
        ['Gloock', 'gloock', '400', 'serif'],
        ['Cormorant Garamond', 'cormorant-garamond', '400,500,400i', 'serif'],
        ['EB Garamond', 'eb-garamond', '400,500,400i', 'serif'],
        ['Libre Caslon Display', 'libre-caslon-display', '400', 'serif'],
        ['Literata', 'literata', '400,500,400i', 'serif'],
        ['Bricolage Grotesque', 'bricolage-grotesque', '400,500,600', 'sans-serif'],
        ['Space Grotesk', 'space-grotesk', '400,500,600', 'sans-serif'],
        ['Syne', 'syne', '500,600,700', 'sans-serif'],
        ['Inter Tight', 'inter-tight', '400,500,600', 'sans-serif'],
        ['Manrope', 'manrope', '500,600,700', 'sans-serif'],
    ];
    const body = [
        ['Instrument Sans', 'instrument-sans', '400,500,600', 'sans-serif'],
        ['Inter', 'inter', '400,500,600', 'sans-serif'],
        ['DM Sans', 'dm-sans', '400,500,600', 'sans-serif'],
        ['Source Sans 3', 'source-sans-3', '400,600', 'sans-serif'],
        ['IBM Plex Sans', 'ibm-plex-sans', '400,500,600', 'sans-serif'],
        ['Work Sans', 'work-sans', '400,500,600', 'sans-serif'],
        ['Figtree', 'figtree', '400,500,600', 'sans-serif'],
        ['Manrope', 'manrope', '400,500,600', 'sans-serif'],
        ['Public Sans', 'public-sans', '400,500,600', 'sans-serif'],
        ['Geologica', 'geologica', '400,500,600', 'sans-serif'],
        ['Literata', 'literata', '400,600', 'serif'],
        ['Newsreader', 'newsreader', '400,600', 'serif'],
    ];
    // Curated pairings: [label, display, body].
    const pairings = [
        ['Current: Instrument Serif + Instrument Sans', 'Instrument Serif', 'Instrument Sans'],
        ['Fraunces + Inter (warm, editorial)', 'Fraunces', 'Inter'],
        ['Newsreader + Inter (calm newspaper)', 'Newsreader', 'Inter'],
        ['Playfair Display + Source Sans 3 (classic magazine)', 'Playfair Display', 'Source Sans 3'],
        ['DM Serif Display + DM Sans (bold and clean)', 'DM Serif Display', 'DM Sans'],
        ['Young Serif + Figtree (friendly)', 'Young Serif', 'Figtree'],
        ['Gloock + Work Sans (punchy serif)', 'Gloock', 'Work Sans'],
        ['Cormorant Garamond + Manrope (elegant)', 'Cormorant Garamond', 'Manrope'],
        ['EB Garamond + IBM Plex Sans (bookish, technical)', 'EB Garamond', 'IBM Plex Sans'],
        ['Libre Caslon Display + Public Sans (refined)', 'Libre Caslon Display', 'Public Sans'],
        ['Literata + Literata (all serif, reading-first)', 'Literata', 'Literata'],
        ['Bricolage Grotesque + Inter (modern studio)', 'Bricolage Grotesque', 'Inter'],
        ['Space Grotesk + IBM Plex Sans (developer)', 'Space Grotesk', 'IBM Plex Sans'],
        ['Syne + DM Sans (designery)', 'Syne', 'DM Sans'],
        ['Inter Tight + Inter (clean, product)', 'Inter Tight', 'Inter'],
        ['Manrope + Manrope (geometric, minimal)', 'Manrope', 'Manrope'],
    ];

    const KEY = 'font-lab';
    const root = document.documentElement;
    const $ = (id) => document.getElementById(id);
    const loaded = new Set();
    const find = (list, name) => list.find((font) => font[0] === name);

    const load = ([name, slug, weights]) => {
        if (loaded.has(slug)) return;
        loaded.add(slug);
        const link = document.createElement('link');
        link.rel = 'stylesheet';
        link.href = `https://fonts.bunny.net/css?family=${slug}:${weights}&display=swap`;
        document.head.appendChild(link);
    };

    const save = (state) => { try { localStorage.setItem(KEY, JSON.stringify(state)); } catch {} };
    const read = () => { try { return JSON.parse(localStorage.getItem(KEY) || 'null'); } catch { return null; } };

    const apply = (state) => {
        const d = find(display, state.display) ?? display[0];
        const b = find(body, state.body) ?? body[0];
        load(d); load(b);
        root.style.setProperty('--font-display', `'${d[0]}', ${d[3]}`);
        root.style.setProperty('--font-body', `'${b[0]}', ${b[3]}`);
        // font-size-adjust in ex units: 0.5 is roughly neutral; scale it for the slider.
        if (state.size !== 100) {
            root.dataset.fontLabScale = '';
            root.style.setProperty('--font-lab-adjust', (0.5 * state.size / 100).toFixed(3));
        } else {
            delete root.dataset.fontLabScale;
            root.style.removeProperty('--font-lab-adjust');
        }
        $('font-lab-display').value = d[0];
        $('font-lab-body').value = b[0];
        $('font-lab-size').value = state.size;
        $('font-lab-size-out').textContent = `${state.size}%`;
        const match = pairings.findIndex((p) => p[1] === d[0] && p[2] === b[0]);
        $('font-lab-pairing').value = match === -1 ? '' : String(match);
        save(state);
    };

    const fill = (select, items, label) => {
        for (const [index, item] of items.entries()) {
            const option = document.createElement('option');
            option.value = label ? String(index) : item[0];
            option.textContent = label ? item[0] : item[0];
            select.appendChild(option);
        }
    };
    fill($('font-lab-pairing'), pairings, true);
    $('font-lab-pairing').insertAdjacentHTML('afterbegin', '<option value="" disabled>Custom</option>');
    fill($('font-lab-display'), display, false);
    fill($('font-lab-body'), body, false);

    let state = read() ?? { display: 'Instrument Serif', body: 'Instrument Sans', size: 100 };
    apply(state);

    const set = (patch) => { state = { ...state, ...patch }; apply(state); };
    const step = (delta) => {
        const current = pairings.findIndex((p) => p[1] === state.display && p[2] === state.body);
        const next = (current + delta + pairings.length) % pairings.length;
        set({ display: pairings[next][1], body: pairings[next][2] });
    };

    $('font-lab-pairing').addEventListener('change', (e) => { const p = pairings[Number(e.target.value)]; if (p) set({ display: p[1], body: p[2] }); });
    $('font-lab-display').addEventListener('change', (e) => set({ display: e.target.value }));
    $('font-lab-body').addEventListener('change', (e) => set({ body: e.target.value }));
    $('font-lab-size').addEventListener('input', (e) => set({ size: Number(e.target.value) }));
    $('font-lab-prev').addEventListener('click', () => step(-1));
    $('font-lab-next').addEventListener('click', () => step(1));
    $('font-lab-reset').addEventListener('click', () => set({ display: 'Instrument Serif', body: 'Instrument Sans', size: 100 }));

    const toggle = document.querySelector('.font-lab-toggle');
    const panel = $('font-lab-panel');
    const setOpen = (open) => { panel.hidden = !open; toggle.setAttribute('aria-expanded', String(open)); try { localStorage.setItem(KEY + ':open', open ? '1' : ''); } catch {} };
    toggle.addEventListener('click', () => setOpen(panel.hidden));
    try { if (localStorage.getItem(KEY + ':open')) setOpen(true); } catch {}

    document.addEventListener('keydown', (e) => {
        if (panel.hidden || ['INPUT', 'SELECT', 'TEXTAREA'].includes(document.activeElement?.tagName)) return;
        if (e.key === 'ArrowRight') step(1);
        if (e.key === 'ArrowLeft') step(-1);
    });
})();
</script>
