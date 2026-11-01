/**
 * Samtalespor spatial discussion activity.
 *
 * @package    mod_spatialforum
 * @copyright  2026 Anders Christian Sloth
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// This file is part of Moodle - http://moodle.org/

import * as Fragment from 'core/fragment';
import * as Templates from 'core/templates';

/** Activity-wide map with one unfolding post and explicit, per-user read persistence. */
export const init = () => {
    const root = document.querySelector('[data-region="spatialforum"]');
    if (!root || root.dataset.initialised) {
        return;
    }
    root.dataset.initialised = '1';
    const stage = root.querySelector('[data-region="stage"]');
    const viewport = root.querySelector('[data-region="viewport"]');
    const svg = root.querySelector('[data-region="connectors"]');
    const route = root.querySelector('[data-region="route"]');
    const search = root.querySelector('[data-region="search"]');
    const onlyUnread = root.querySelector('[data-region="only-unread"]');
    const viewButtons = Array.from(root.querySelectorAll('[data-view]'));
    const densityButton = root.querySelector('[data-density-toggle]');
    const stateKey = 'mod_spatialforum:' + root.dataset.cmid;
    let saved = {};
    try {
        saved = JSON.parse(window.sessionStorage.getItem(stateKey) || '{}') || {};
    } catch (error) {
        // Navigation and reading also work when browser storage is unavailable.
    }
    let view = ['map', 'list'].includes(root.dataset.initialView) ? root.dataset.initialView : saved.view;
    if (!['map', 'list'].includes(view)) {
        view = null;
    }
    let density = saved.density === 'compressed' ? 'compressed' : 'comfortable';
    let overview = {top: Number(saved.top) || 0, left: Number(saved.left) || 0};
    const scrollHost = () => {
        let node = root.parentElement;
        while (node) {
            if (/(auto|scroll)/.test(window.getComputedStyle(node).overflowY)) {
                return node;
            }
            node = node.parentElement;
        }
        return document.scrollingElement || document.documentElement;
    };
    const remember = () => {
        try {
            // Store only presentation state per tab, never post bodies or drafts.
            window.sessionStorage.setItem(stateKey, JSON.stringify({...overview, view, density}));
        } catch (error) {
            // Storage is an optional enhancement.
        }
    };
    const cards = Array.from(root.querySelectorAll('[data-post-id]'));
    const byId = new Map(cards.map(card => [card.dataset.postId, card]));
    const parents = new Map();
    const pending = new Set();
    const failed = new Set();
    const measurements = new Map();
    const detailRequests = new Map();
    const ns = 'http://www.w3.org/2000/svg';
    let active = null;
    let positions = new Map();
    let animation = 0;
    let relayout = 0;
    let lastWidth = 0;
    let restoring = true;

    // Imported/missing/cyclic references must never hide an otherwise accessible post.
    cards.forEach(card => {
        const parent = card.dataset.parentId;
        parents.set(card.dataset.postId, byId.has(parent) && parent !== card.dataset.postId ? parent : null);
    });
    cards.forEach(card => {
        const visited = new Set([card.dataset.postId]);
        let parent = parents.get(card.dataset.postId);
        while (parent) {
            if (visited.has(parent)) {
                parents.set(card.dataset.postId, null);
                break;
            }
            visited.add(parent);
            parent = parents.get(parent);
        }
    });
    const ancestry = id => {
        const result = [];
        while (id && byId.has(id)) {
            result.unshift(id);
            id = parents.get(id);
        }
        return result;
    };
    const depths = new Map(cards.map(card => [card.dataset.postId, ancestry(card.dataset.postId).length - 1]));
    const children = new Map();
    cards.forEach(card => {
        const parent = parents.get(card.dataset.postId);
        if (!children.has(parent)) {
            children.set(parent, []);
        }
        children.get(parent).push(card.dataset.postId);
        card.style.setProperty('--post-depth', depths.get(card.dataset.postId));
    });
    // List order follows the conversation hierarchy rather than interleaved creation times.
    const appendTree = id => {
        root.querySelector('.spatialforum-posts').appendChild(byId.get(id));
        (children.get(id) || []).forEach(appendTree);
    };
    (children.get(null) || []).forEach(appendTree);
    // A continuous chain keeps its colour. At a fork each child starts a coloured segment.
    const palette = ['#237ba7', '#ad691b', '#8057ad', '#277e62', '#af4572', '#5264ab'];
    const colours = new Map();
    const colourFor = id => {
        if (colours.has(id)) {
            return colours.get(id);
        }
        const parent = parents.get(id);
        const siblings = parent ? children.get(parent) : [];
        const colour = !parent ? '#ffffff' : root.dataset.coloursEnabled === '0' ? '#dfe7e2' :
            !parents.get(parent) || siblings.length > 1 ?
            palette[(Number(parent) + siblings.indexOf(id)) % palette.length] : colourFor(parent);
        colours.set(id, colour);
        return colour;
    };
    cards.forEach(card => card.style.setProperty('--post-colour', colourFor(card.dataset.postId)));
    const title = card => card.querySelector('.spatialforum-subject').textContent;
    const searchText = card => title(card) + ' ' +
        (card.dataset.searchText ?? card.querySelector('.spatialforum-message')?.textContent ?? '');
    const element = (tag, attributes) => {
        const node = document.createElementNS(ns, tag);
        Object.entries(attributes).forEach(([name, value]) => node.setAttribute(name, value));
        return node;
    };
    const updateRoute = () => {
        const path = ancestry(active);
        route.replaceChildren();
        path.forEach((id, index) => {
            if (index) {
                const arrow = document.createElement('span');
                arrow.textContent = '›';
                arrow.setAttribute('aria-hidden', 'true');
                route.appendChild(arrow);
            }
            const link = document.createElement('a');
            link.href = '#post-' + id;
            link.dataset.openPost = id;
            link.textContent = title(byId.get(id));
            if (id === active) {
                link.setAttribute('aria-current', 'location');
            }
            route.appendChild(link);
        });
        if (!path.length) {
            const label = document.createElement('span');
            label.textContent = root.dataset.wholeLabel;
            route.appendChild(label);
        }
        cards.forEach(card => {
            const onPath = path.includes(card.dataset.postId);
            card.classList.toggle('is-on-route', onPath);
            card.classList.toggle('is-muted', !!active && !onPath);
        });
    };
    const updateCount = () => {
        root.querySelector('[data-region="unread-count"]').textContent =
            cards.filter(card => card.dataset.unread === '1').length;
        root.querySelector('[data-next-unread]').disabled = !cards.some(card =>
            card.dataset.unread === '1' && !card.hidden);
    };
    const markRead = async(card) => {
        const id = card.dataset.postId;
        if (card.dataset.readable !== '1' || card.dataset.unread !== '1' || pending.has(id)) {
            return;
        }
        pending.add(id);
        try {
            const body = new URLSearchParams({id: root.dataset.cmid, postid: id, sesskey: root.dataset.sesskey, ajax: '1'});
            const response = await fetch(root.dataset.readUrl, {method: 'POST', credentials: 'same-origin', body});
            if (!response.ok) {
                throw new Error('Read state request failed');
            }
            const data = await response.json();
            if (!data.success || String(data.postid) !== id) {
                throw new Error('Invalid read state response');
            }
            // Do not display a persisted read marker until the server confirms this specific post.
            card.dataset.unread = '0';
            card.classList.remove('is-unread');
            card.querySelector('[data-region="read-state"]').textContent = '✓ ' + root.dataset.readLabel;
            failed.delete(id);
            if (active !== id && onlyUnread.checked) {
                card.hidden = true;
                root.querySelector('[data-region="no-results"]').hidden = cards.some(item => !item.hidden);
            }
            updateCount();
        } catch (error) {
            failed.add(id);
        } finally {
            pending.delete(id);
            root.querySelector('[data-region="read-error"]').hidden = failed.size === 0;
            layout(false);
        }
    };
    const layout = (animate = true) => {
        if (animation) {
            cancelAnimationFrame(animation);
            animation = 0;
        }
        const width = viewport.getBoundingClientRect().width;
        if (!width) {
            return;
        }
        lastWidth = width;
        const maxDepth = Math.max(...depths.values());
        // Columns retain their widths as depth increases; extra layers scroll horizontally.
        const gap = 48;
        const mainColumn = Math.max(320, width * 0.5);
        const replyColumn = Math.max(240, width * 0.25);
        const list = cards.length > 150 || view === 'list' || (!view && width < 640);
        root.classList.toggle('is-list', list);
        root.classList.toggle('is-map', !list);
        viewButtons.forEach(button => {
            button.setAttribute('aria-pressed', String(button.dataset.view === (list ? 'list' : 'map')));
            button.disabled = button.dataset.view === 'map' && cards.length > 150;
        });
        root.querySelector('[data-region="map-limit"]').hidden = cards.length <= 150;
        root.querySelectorAll('a.spatialforum-reply, .spatialforum-hero a, form[data-excerpt-start]').forEach(action => {
            const attribute = action.tagName === 'FORM' ? 'action' : 'href';
            const url = new URL(action.getAttribute(attribute), window.location.href);
            url.searchParams.set('sfview', list ? 'list' : 'map');
            action.setAttribute(attribute, url.toString());
        });
        if (!cards.length) {
            return;
        }
        if (list) {
            stage.style.width = 'auto';
            stage.style.height = 'auto';
            svg.replaceChildren();
            positions = new Map();
            return;
        }
        const widths = Array.from({length: maxDepth + 1}, (_, d) => (d ? replyColumn : mainColumn) - gap);
        if (active) {
            // Keep the opened post in its thread. Widen its column for readable text,
            // then reflow cards and wires together instead of hiding the overview.
            const depth = depths.get(active);
            widths[depth] = Math.max(widths[depth], Math.min(680, Math.max(320, width * 0.5 - gap)));
        }
        const total = widths.reduce((a, b) => a + b, 0) + maxDepth * gap;
        const xs = [];
        const canvasWidth = Math.max(width, total + gap);
        stage.style.width = canvasWidth + 'px';
        let x = gap / 2;
        widths.forEach(w => {
            xs.push(x);
            x += w + gap;
        });
        const heights = new Map();
        const anchors = new Map();
        cards.forEach(card => {
            card.style.width = widths[depths.get(card.dataset.postId)] + 'px';
            const rect = card.getBoundingClientRect();
            heights.set(card.dataset.postId, rect.height);
            anchors.set(card.dataset.postId, card.querySelector('summary').getBoundingClientRect().height / 2);
            measurements.set(card, [rect.width, rect.height]);
        });
        const spans = new Map();
        const measure = id => {
            const kids = children.get(id) || [];
            const stack = kids.reduce((sum, child) => sum + measure(child), 0) + Math.max(0, kids.length - 1) * 24;
            const height = Math.max(heights.get(id), stack);
            spans.set(id, height);
            return height;
        };
        const roots = children.get(null) || [];
        roots.forEach(measure);
        const target = new Map();
        const place = (id, top) => {
            const kids = children.get(id) || [];
            const stack = kids.reduce((sum, child) => sum + spans.get(child), 0) + Math.max(0, kids.length - 1) * 24;
            target.set(id, {x: xs[depths.get(id)], y: top + (spans.get(id) - heights.get(id)) / 2,
                w: widths[depths.get(id)], h: heights.get(id)});
            let childTop = top + (spans.get(id) - stack) / 2;
            kids.forEach(child => {
                place(child, childTop);
                childTop += spans.get(child) + 24;
            });
        };
        let top = 45;
        roots.forEach(id => {
            place(id, top);
            top += spans.get(id) + 36;
        });
        const height = top + 20;
        stage.style.height = height + 'px';
        svg.style.width = canvasWidth + 'px';
        svg.style.height = height + 'px';
        svg.setAttribute('viewBox', '0 0 ' + canvasWidth + ' ' + height);
        svg.replaceChildren();
        widths.forEach((w, d) => {
            const left = xs[d] - 5;
            const right = xs[d] + w + 4;
            svg.appendChild(element('path', {d: `M ${left} 34 L ${right} 20 L ${right + 5} ${height - 20} ` +
                `L ${left - 3} ${height - 6} Z`, fill: 'var(--sf-soft)', opacity: '.45'}));
            const label = element('text', {x: xs[d] + 4, y: 17, fill: 'var(--sf-muted)', 'font-size': 11});
            label.textContent = d ? root.dataset.depthLabel + ' ' + (d + 1) : root.dataset.rootLabel;
            svg.appendChild(label);
        });
        const wires = new Map();
        const path = ancestry(active);
        parents.forEach((parent, id) => {
            if (!parent) {
                return;
            }
            const wire = element('path', {fill: 'none', stroke: colourFor(id), 'data-wire-post': id,
                'vector-effect': 'non-scaling-stroke', 'stroke-width': path.includes(id) ? 3.5 : 2.5});
            wires.set(id, wire);
            svg.appendChild(wire);
        });
        const paint = points => {
            positions = points;
            points.forEach((p, id) => {
                byId.get(id).style.left = p.x + 'px';
                byId.get(id).style.top = p.y + 'px';
            });
            wires.forEach((wire, id) => {
                const a = points.get(parents.get(id));
                const b = points.get(id);
                const start = a.x + a.w;
                const end = b.x;
                const middle = (start + end) / 2;
                const ay = a.y + anchors.get(parents.get(id));
                const by = b.y + anchors.get(id);
                wire.setAttribute('d', `M ${start} ${ay} C ${middle} ${ay} ${middle} ${by} ${end} ${by}`);
            });
        };
        if (!animate || !positions.size || window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            paint(target);
            return;
        }
        const old = positions;
        const start = performance.now();
        const tick = now => {
            const t = Math.min(1, (now - start) / 280);
            const eased = 1 - Math.pow(1 - t, 3);
            const points = new Map();
            target.forEach((end, id) => {
                const a = old.get(id) || end;
                const point = {};
                ['x', 'y', 'w', 'h'].forEach(key => point[key] = a[key] + (end[key] - a[key]) * eased);
                points.set(id, point);
            });
            paint(points);
            if (t < 1) {
                animation = requestAnimationFrame(tick);
            } else {
                animation = 0;
                paint(target);
            }
        };
        animation = requestAnimationFrame(tick);
    };
    const applyDensity = () => {
        const compressed = density === 'compressed';
        root.classList.toggle('is-compressed', compressed);
        densityButton.setAttribute('aria-pressed', String(compressed));
    };
    const writeURL = (id, replace = false) => {
        const url = new URL(window.location.href);
        if (id) {
            url.searchParams.set('postid', id);
        } else {
            url.searchParams.delete('postid');
        }
        url.hash = id ? 'post-' + id : '';
        url.searchParams.set('sfview', root.classList.contains('is-list') ? 'list' : 'map');
        if (url.toString() !== window.location.href) {
            window.history[replace ? 'replaceState' : 'pushState'](window.history.state, '', url.toString());
        }
        remember();
    };
    const loadDetails = card => {
        const region = card.querySelector('[data-deferred]');
        if (!region) {
            return Promise.resolve(true);
        }
        const id = card.dataset.postId;
        if (detailRequests.has(id)) {
            return detailRequests.get(id);
        }
        const request = (async() => {
            const status = region.querySelector('[data-region="detail-status"]');
            const link = region.querySelector('[data-load-detail]');
            region.setAttribute('aria-busy', 'true');
            status.textContent = root.dataset.loadingLabel;
            try {
                const result = await new Promise((resolve, reject) => {
                    Fragment.loadFragment('mod_spatialforum', 'post_detail', Number(root.dataset.contextId), {postid: id})
                        .then((html, js) => resolve({html, js}), reject);
                });
                await Templates.replaceNodeContents(region, result.html, result.js);
                region.removeAttribute('data-deferred');
                root.querySelector('[data-region="feedback"]').textContent = root.dataset.loadedLabel;
                layout(false);
                return true;
            } catch (error) {
                status.textContent = root.dataset.loadErrorLabel;
                link.textContent = root.dataset.retryLabel;
                const fallback = document.createElement('a');
                fallback.href = link.href;
                fallback.textContent = root.dataset.fallbackLabel;
                fallback.className = 'd-block';
                region.replaceChildren(status, link, fallback);
                return false;
            } finally {
                region.removeAttribute('aria-busy');
                detailRequests.delete(id);
            }
        })();
        detailRequests.set(id, request);
        return request;
    };
    const readLoadedCard = async(card) => {
        if (await loadDetails(card) && active === card.dataset.postId && card.hasAttribute('open')) {
            void markRead(card);
        }
    };
    const openCard = (card, focus = false, record = true) => {
        if (active === card.dataset.postId) {
            return;
        }
        if (!active && !restoring) {
            overview = {top: scrollHost().scrollTop || 0, left: viewport.scrollLeft || 0};
        }
        cards.forEach(other => {
            if (other !== card) {
                other.removeAttribute('open');
                const query = search.value.trim().toLocaleLowerCase();
                other.hidden = !searchText(other)
                    .toLocaleLowerCase().includes(query) || (onlyUnread.checked && other.dataset.unread !== '1');
            }
        });
        if (!card.hasAttribute('open')) {
            card.setAttribute('open', '');
        }
        active = card.dataset.postId;
        card.hidden = false;
        root.querySelector('[data-region="no-results"]').hidden = true;
        updateCount();
        updateRoute();
        layout(false);
        if (record) {
            writeURL(active);
        }
        if (card.querySelector('[data-deferred]')) {
            void readLoadedCard(card);
        } else {
            void markRead(card);
        }
        if (focus) {
            card.querySelector('summary').focus({preventScroll: true});
            card.scrollIntoView({block: 'nearest', inline: 'nearest'});
        }
    };
    const closeCard = (card, focus = true, record = true) => {
        card.removeAttribute('open');
        active = null;
        const query = search.value.trim().toLocaleLowerCase();
        card.hidden = !searchText(card)
            .toLocaleLowerCase().includes(query) || (onlyUnread.checked && card.dataset.unread !== '1');
        root.querySelector('[data-region="no-results"]').hidden = cards.some(item => !item.hidden);
        updateCount();
        updateRoute();
        layout(false);
        viewport.scrollLeft = overview.left;
        scrollHost().scrollTop = overview.top;
        if (record) {
            writeURL(null);
        }
        if (focus) {
            const target = card.hidden ? search : card.querySelector('summary');
            target.focus({preventScroll: true});
        }
    };
    root.classList.add('js-enabled');
    root.querySelector('[data-region="tools"]').hidden = false;
    applyDensity();
    const applyFilters = () => {
        const query = search.value.trim().toLocaleLowerCase();
        const filtering = !!query || onlyUnread.checked;
        if (active) {
            closeCard(byId.get(active), false);
        }
        if (filtering) {
            view = 'list';
        }
        cards.forEach(card => {
            const text = searchText(card);
            card.hidden = !text.toLocaleLowerCase().includes(query) ||
                (onlyUnread.checked && card.dataset.unread !== '1');
        });
        root.querySelector('[data-region="filter-status"]').hidden = !filtering;
        const shown = cards.filter(card => !card.hidden).length;
        root.querySelector('[data-region="filter-status"]').textContent = shown + ' ' + root.dataset.resultsLabel;
        root.querySelector('[data-region="no-results"]').hidden = !cards.length || cards.some(card => !card.hidden);
        updateCount();
        layout(false);
        writeURL(null, true);
    };
    search.addEventListener('input', applyFilters);
    onlyUnread.addEventListener('change', applyFilters);
    viewButtons.forEach(button => button.addEventListener('click', () => {
        view = button.dataset.view;
        if (view === 'map') {
            search.value = '';
            onlyUnread.checked = false;
            cards.forEach(card => card.hidden = false);
            root.querySelector('[data-region="filter-status"]').hidden = true;
            root.querySelector('[data-region="no-results"]').hidden = true;
        }
        layout(false);
        writeURL(active, true);
        updateCount();
    }));
    densityButton.addEventListener('click', () => {
        density = density === 'compressed' ? 'comfortable' : 'compressed';
        applyDensity();
        remember();
        layout(false);
    });
    root.querySelector('[data-next-unread]').addEventListener('click', () => {
        const index = cards.findIndex(card => card.dataset.postId === active);
        const ordered = cards.slice(index + 1).concat(cards.slice(0, index + 1));
        const next = ordered.find(card => card.dataset.unread === '1' && !card.hidden);
        if (next) {
            openCard(next, true);
        }
    });
    cards.forEach(card => card.addEventListener('toggle', () => {
        if (card.hasAttribute('open')) {
            openCard(card, true);
        } else if (active === card.dataset.postId) {
            closeCard(card);
        }
    }));
    root.addEventListener('click', event => {
        const load = event.target.closest('[data-load-detail]');
        if (load && !event.ctrlKey && !event.metaKey && !event.shiftKey && !event.altKey && event.button === 0) {
            event.preventDefault();
            void readLoadedCard(load.closest('[data-post-id]'));
        }
        const link = event.target.closest('[data-open-post]');
        if (link && !event.ctrlKey && !event.metaKey && !event.shiftKey && !event.altKey && event.button === 0) {
            const card = byId.get(link.dataset.openPost);
            if (card) {
                event.preventDefault();
                openCard(card, true);
            }
        }
        const close = event.target.closest('[data-close]');
        if (close) {
            closeCard(close.closest('[data-post-id]'));
        }
        const copy = event.target.closest('[data-copy-link]');
        if (copy) {
            const feedback = root.querySelector('[data-region="feedback"]');
            Promise.resolve().then(() => window.navigator.clipboard.writeText(window.location.href))
                .then(() => feedback.textContent = root.dataset.copiedLabel)
                .catch(() => feedback.textContent = root.dataset.copyFailedLabel);
        }
    });
    root.addEventListener('keydown', event => {
        if (event.key === 'Escape' && active && !event.defaultPrevented) {
            event.preventDefault();
            closeCard(byId.get(active));
        }
    });
    const initial = byId.get(window.location.hash.replace(/^#post-/, '')) || cards.find(card => card.hasAttribute('open'));
    updateCount();
    if (initial) {
        layout(false);
        openCard(initial, true, false);
    } else {
        updateRoute();
        layout(false);
    }
    restoring = false;
    window.addEventListener('popstate', () => {
        const url = new URL(window.location.href);
        const selectedView = url.searchParams.get('sfview');
        if (['map', 'list'].includes(selectedView)) {
            view = selectedView;
        }
        const target = byId.get(url.hash.replace(/^#post-/, '')) || byId.get(url.searchParams.get('postid'));
        if (target) {
            openCard(target, true, false);
        } else if (active) {
            closeCard(byId.get(active), true, false);
        }
        layout(false);
    });
    if (typeof ResizeObserver !== 'undefined') {
        const observer = new ResizeObserver(entries => {
            const changed = entries.some(entry => {
                const rect = entry.target.getBoundingClientRect();
                if (entry.target === viewport) {
                    return Math.abs(lastWidth - rect.width) > 1;
                }
                const previous = measurements.get(entry.target);
                return !root.classList.contains('is-list') &&
                    (!previous || Math.abs(previous[0] - rect.width) > 1 || Math.abs(previous[1] - rect.height) > 1);
            });
            if (changed && !relayout) {
                relayout = requestAnimationFrame(() => {
                    relayout = 0;
                    layout(false);
                });
            }
        });
        observer.observe(viewport);
        cards.forEach(card => observer.observe(card));
    } else {
        window.addEventListener('resize', () => layout(false));
        root.addEventListener('load', () => layout(false), true);
    }
};
