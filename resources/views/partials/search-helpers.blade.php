{{-- Shared search helpers · ship one set of functions on `window` for the
     three search-bearing variants (searchable-alpine, multi-alpine,
     tags-alpine) to call from their `get filtered()` and template
     `x-html` bindings. @once-guarded at the inclusion site so multiple
     instances on one page don't redefine. --}}
<script data-lc-search-helpers>
    (function () {
        if (window.__lcSearchHelpers) return;
        window.__lcSearchHelpers = true;

        // Live-region throttle · screen readers chatter when liveMessage
        // updates on every keystroke. lcMakeAnnouncer returns a setter that
        // coalesces updates within `delay` ms so a fast typist gets one
        // settled announcement rather than 6.
        window.lcMakeAnnouncer = function (setter, delay = 280) {
            let timer = null;
            let pending = null;
            return function (msg) {
                pending = msg;
                if (timer) return;
                timer = setTimeout(() => {
                    setter(pending);
                    timer = null;
                }, delay);
            };
        };

        // Body-scroll lock for the mobile bottom-sheet · prevents the page
        // behind the backdrop from scrolling under the user's thumb on iOS.
        // Reference-counted because multiple open sheets shouldn't unlock
        // each other when the first one closes.
        let bodyLockCount = 0;
        let bodyLockPrev = null;
        window.lcLockBodyScroll = function () {
            if (bodyLockCount === 0) {
                bodyLockPrev = document.documentElement.style.overflow;
                document.documentElement.style.overflow = 'hidden';
            }
            bodyLockCount++;
        };
        window.lcUnlockBodyScroll = function () {
            if (bodyLockCount === 0) return;
            bodyLockCount--;
            if (bodyLockCount === 0) {
                document.documentElement.style.overflow = bodyLockPrev || '';
                bodyLockPrev = null;
            }
        };

        // Collision-proof id-safe encoding · the original
        // `replace(non-alnum, '_NN')` collided when an input string already
        // contained the literal escape sequence (e.g. 'héllo' and 'h_e9llo'
        // both encoded to the same id). Doubling input underscores first
        // makes the escape space disjoint from the input space.
        window.lcSafeId = function (key) {
            return String(key)
                .replace(/_/g, '__')
                .replace(/[^a-zA-Z0-9_-]/g, (c) => '_' + c.charCodeAt(0).toString(16));
        };

        // Memoize a filter run · returns a stable closure that short-circuits
        // when called with the same (items, query) pair as last time. The
        // ranking + highlight pipeline is O(items × tokens) with allocation
        // per item, so Alpine's per-render getter re-evaluation makes this
        // memo a meaningful win on large lists.
        //
        // Usage from a variant's `get filtered()`:
        //   get filtered() { return (this._memo ??= window.lcMakeFilter())(this.items, this.query); }
        window.lcMakeFilter = function () {
            let lastItems = null;
            let lastQuery = null;
            let lastResult = null;
            return function (items, query) {
                if (items === lastItems && query === lastQuery && lastResult) {
                    return lastResult;
                }
                lastItems = items;
                lastQuery = query;
                lastResult = window.lcRankItems(items, query);
                return lastResult;
            };
        };

        // Per-array normalisation cache · the rank loop reads the lowercase
        // forms of title / subtitle / key for every item every keystroke,
        // which is wasted work given items rarely change between queries.
        // WeakMap keyed on the items array reference so a fresh items array
        // (after remote-search swap) gets a fresh cache and the old one is
        // GC-able once Alpine drops its reference.
        const itemsCache = new WeakMap();
        function normalisedItems(items) {
            const hit = itemsCache.get(items);
            if (hit) return hit;
            const norm = items.map((o) => ({
                item: o,
                titleLc: (o.title || '').toLowerCase(),
                subtitleLc: (o.subtitle || '').toLowerCase(),
                keyLc: (o.key || '').toLowerCase(),
            }));
            itemsCache.set(items, norm);
            return norm;
        }

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
            const norm = normalisedItems(items);
            for (const entry of norm) {
                const item = entry.item;
                const titleLc = entry.titleLc;
                const subtitleLc = entry.subtitleLc;
                const keyLc = entry.keyLc;
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

        // Remote search hook · debounced fetch with AbortController so only
         // the latest query's response wins. Returns a controller object
         // that the variant's Alpine data wires into its $watch('query').
        //
        // config callbacks (passed as fns so the host can read live state):
        //   url()         · resolves to the URL to hit, or '' to skip
        //   debounceMs()  · ms to debounce (default 250)
        //   onResult(items) · receives the parsed JSON body
        //   onLoading(bool) · toggled around the in-flight window
        //   onError(err)    · optional; defaults to console.error
        window.lcMakeRemoteSearch = function (config) {
            let timer = null;
            let ctrl = null;
            const fail = config.onError || ((e) => console.error('[lc-select] search:', e));
            const ctl = {
                queue(query) {
                    if (timer) clearTimeout(timer);
                    if (!config.url()) return;
                    timer = setTimeout(() => ctl.run(query), config.debounceMs() || 250);
                },
                run(query) {
                    const base = config.url();
                    if (!base) return;
                    if (ctrl) ctrl.abort();
                    ctrl = new AbortController();
                    config.onLoading && config.onLoading(true);
                    const sep = base.indexOf('?') >= 0 ? '&' : '?';
                    const url = base + sep + 'q=' + encodeURIComponent(query || '');
                    // Retry once on transient failures (network drop or 5xx)
                    // before reporting · 1xx/2xx/3xx/4xx pass through. Aborts
                    // are surfaced as-is so a fresh keystroke can replace the
                    // request without retrying the cancelled one.
                    const attempt = (n) => fetch(url, { signal: ctrl.signal, headers: { Accept: 'application/json' } })
                        .then((r) => {
                            if (r.ok) return r.json();
                            if (r.status >= 500 && n < 1) {
                                return new Promise((res) => setTimeout(res, 200))
                                    .then(() => attempt(n + 1));
                            }
                            throw new Error('HTTP ' + r.status);
                        })
                        .catch((e) => {
                            if (e && e.name === 'AbortError') throw e;
                            if (n < 1 && (!e.message || !e.message.startsWith('HTTP'))) {
                                // Network-layer error · one retry then give up.
                                return new Promise((res) => setTimeout(res, 200))
                                    .then(() => attempt(n + 1));
                            }
                            throw e;
                        });
                    attempt(0)
                        .then((items) => {
                            config.onResult && config.onResult(Array.isArray(items) ? items : (items.items || []));
                            config.onLoading && config.onLoading(false);
                        })
                        .catch((e) => {
                            if (e && e.name === 'AbortError') return;
                            fail(e);
                            config.onLoading && config.onLoading(false);
                        });
                },
                cancel() {
                    if (timer) { clearTimeout(timer); timer = null; }
                    if (ctrl) { ctrl.abort(); ctrl = null; }
                    config.onLoading && config.onLoading(false);
                },
            };
            return ctl;
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
                // <span> not <mark> · most screen readers leave <span>
                // unannounced where some (JAWS verbose, NVDA browse mode)
                // pre/post-announce <mark> with "marked" / "marked end".
                out += '<span class="lc-select__match">' + safe(str.slice(s, e)) + '</span>';
                pos = e;
            }
            if (pos < str.length) out += safe(str.slice(pos));
            return out;
        };
    })();
</script>
