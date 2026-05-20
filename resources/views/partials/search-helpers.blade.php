{{-- Shared search helpers · ship one set of functions on `window` for the
     three search-bearing variants (searchable-alpine, multi-alpine,
     tags-alpine) to call from their `get filtered()` and template
     `x-html` bindings. @once-guarded at the inclusion site so multiple
     instances on one page don't redefine. --}}
<script data-lc-search-helpers>
    (function () {
        if (window.__lcSearchHelpers) return;
        window.__lcSearchHelpers = true;

        // Score an items array against a multi-token query. Tokens are AND-ed
        // (every token must hit somewhere); ties broken by where the match
        // lands · prefix-on-title beats mid-word beats subtitle. Returns
        // new objects with `_hl` holding the match ranges for highlighting.
        window.lcRankItems = function (items, query) {
            const raw = (query || '').trim();
            if (!raw) {
                return items.map((o) => Object.assign({}, o, { _hl: null }));
            }
            const tokens = raw.toLowerCase().split(/\s+/).filter(Boolean);
            const out = [];
            for (const item of items) {
                const titleLc = (item.title || '').toLowerCase();
                const subtitleLc = (item.subtitle || '').toLowerCase();
                const keyLc = (item.key || '').toLowerCase();
                let score = 0;
                const titleRanges = [];
                const subtitleRanges = [];
                let allMatched = true;
                for (const tok of tokens) {
                    const ti = titleLc.indexOf(tok);
                    const si = subtitleLc.indexOf(tok);
                    const ki = keyLc.indexOf(tok);
                    if (ti < 0 && si < 0 && ki < 0) { allMatched = false; break; }
                    // Score: prefix-on-title >> mid-title >> key-prefix >>
                    // key-mid >> subtitle. Earlier hits weigh more.
                    if (ti === 0) score += 1000;
                    else if (ti > 0) score += 200 - Math.min(ti, 199);
                    else if (ki === 0) score += 80;
                    else if (ki >= 0) score += 40;
                    else if (si >= 0) score += 20;
                    if (ti >= 0) titleRanges.push([ti, ti + tok.length]);
                    if (si >= 0) subtitleRanges.push([si, si + tok.length]);
                }
                if (!allMatched) continue;
                // Shorter titles tie-break above longer ones (so a 4-char
                // exact prefix wins over a 24-char that also prefix-matches).
                score -= titleLc.length * 0.01;
                out.push(Object.assign({}, item, {
                    _hl: { title: titleRanges, subtitle: subtitleRanges },
                    _score: score,
                }));
            }
            out.sort((a, b) => b._score - a._score);
            return out;
        };

        // Escape HTML entities for safe interpolation into innerHTML.
        window.lcEscapeHtml = function (s) {
            return String(s == null ? '' : s).replace(/[&<>"']/g, (c) => ({
                '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
            }[c]));
        };

        // Wrap match ranges in <mark class="lc-select__match"> · merges any
        // overlapping ranges first so we never emit nested or duplicate
        // <mark> elements.
        window.lcHighlightHtml = function (text, ranges) {
            const safe = window.lcEscapeHtml;
            const str = text == null ? '' : String(text);
            if (!ranges || ranges.length === 0) return safe(str);
            const sorted = ranges.slice().sort((a, b) => a[0] - b[0]);
            const merged = [sorted[0].slice()];
            for (let i = 1; i < sorted.length; i++) {
                const r = sorted[i];
                const last = merged[merged.length - 1];
                if (r[0] <= last[1]) {
                    last[1] = Math.max(last[1], r[1]);
                } else {
                    merged.push(r.slice());
                }
            }
            let out = '';
            let pos = 0;
            for (const [s, e] of merged) {
                if (s > pos) out += safe(str.slice(pos, s));
                out += '<mark class="lc-select__match">' + safe(str.slice(s, e)) + '</mark>';
                pos = e;
            }
            if (pos < str.length) out += safe(str.slice(pos));
            return out;
        };
    })();
</script>
