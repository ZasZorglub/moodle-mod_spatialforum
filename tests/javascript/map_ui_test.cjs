/**
 * Samtalespor spatial discussion activity.
 *
 * @package    mod_spatialforum
 * @copyright  2026 Anders Christian Sloth
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// DOM regressions for the shipped template and both AMD source/build. No browser hit-test claim.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const {parseHTML} = require('linkedom');
const Mustache = require('mustache');
const plugin = path.join(__dirname, '../..');
const template = fs.readFileSync(path.join(plugin, 'templates/main.mustache'), 'utf8');
const partial = fs.readFileSync(path.join(plugin, 'templates/reply_context.mustache'), 'utf8');
const source = fs.readFileSync(path.join(plugin, 'amd/src/spatialforum.js'), 'utf8');
const built = fs.readFileSync(path.join(plugin, 'amd/build/spatialforum.min.js'), 'utf8');
const labels = {readpost: 'Read', unread: 'Unread', maintrack: 'Main track', depth: 'Depth', closepost: 'Close'};

async function exercise(script, cyclic = false, mode = '') {
    const posts = [
        {id: 10, parentid: 0}, {id: 11, parentid: 10, unread: true},
        {id: 12, parentid: 11, unread: true}, {id: 13, parentid: 10},
        {id: 14, parentid: 13, unread: true}, {id: 15, parentid: 0},
    ].map(p => ({...p, subject: 'Post ' + p.id, preview: 'Two lines of the actual answer, not a parent quote.',
        message: '<p>Full content for post ' + p.id + '</p>', author: 'Participant', colour: '#54b9ec',
        cantrackread: true, readlabel: 'Read', canreply: true, replyurl: '/reply?id=' + p.id,
        creatediso: '2026-10-25T12:00:00+00:00', createdshort: 'Created 25/10, 13:00',
        createdlabel: 'Created 25/10/2026, 13:00', edited: p.id === 12,
        editediso: '2026-10-25T13:00:00+00:00', editedshort: 'Edited 25/10, 14:00',
        editedlabel: 'Edited 25/10/2026, 14:00',
        showlikes: true, canlike: true, nextreaction: 1, nextdislike: -1, likecount: 0,
        dislikecount: 0, likelabel: 'Like', dislikelabel: 'Dislike', canmoderate: true, candelete: true, trackid: 7}));
    if (cyclic) {
        posts[0].parentid = 12;
    }
    const html = Mustache.render(template, {posts, hasposts: true, cmid: 42, sesskey: 'token',
        readurl: '/read.php', readcount: 3, postcount: 6, likeurl: '/like.php',
        moderationurl: '/action.php', enablecolors: mode === 'neutral' ? '0' : '1',
        str: () => text => labels[text.split(',')[0].trim()] || text.split(',')[0].trim()},
    {'mod_spatialforum/reply_context': partial});
    const {document, window} = parseHTML(html);
    let width = 940;
    const observers = [];
    const requests = [];
    let failNext12 = true;
    let releaseRead11;
    let observedFocus = null;
    window.HTMLElement.prototype.focus = function() { observedFocus = this; };
    window.HTMLElement.prototype.getBoundingClientRect = function() {
        return {width: this.matches('.spatialforum-viewport, .mod-spatialforum') ? width : parseFloat(this.style.width) || 150,
            height: this.classList.contains('spatialforum-post') ? (this.hasAttribute('open') ? 460 : 160) : 0};
    };
    const root = document.querySelector('[data-region="spatialforum"]');
    window.HTMLElement.prototype.scrollIntoView = function() {};
    const storage = new Map();
    const location = {href: 'https://example.test/mod/spatialforum/view.php?id=42', hash: ''};
    const events = {};
    const history = {state: null};
    const navigations = [];
    for (const method of ['pushState', 'replaceState']) {
        history[method] = (state, _, href) => {
            navigations.push({method, href});
            location.href = href;
            location.hash = new URL(href).hash;
        };
    }
    const scope = {document, window: {location, history,
        sessionStorage: {getItem: key => storage.get(key), setItem: (key, value) => storage.set(key, value)},
        getComputedStyle: () => ({overflowY: 'visible'}),
        matchMedia: () => ({matches: true}), confirm: () => false,
        addEventListener: (name, fn) => {events[name] = fn;}}, URL, URLSearchParams, performance: {now: () => 0},
    requestAnimationFrame: callback => {callback(0); return 1;}, cancelAnimationFrame: () => {},
    ResizeObserver: class {constructor(cb) {observers.push(cb);} observe() {}},
    fetch: async(url, options) => {
        const params = new URLSearchParams(options.body);
        const id = params.get('postid');
        requests.push({url, options, params});
        if (mode === 'unread-late' && id === '11') {
            await new Promise(resolve => { releaseRead11 = resolve; });
        }
        if (id === '12' && failNext12) {
            failNext12 = false;
            return {ok: false};
        }
        return {ok: true, json: async() => ({success: true, postid: Number(id)})};
    }};
    const context = vm.createContext(scope);
    if (script === source) {
        vm.runInContext(script.replace('export const init', 'const init') + ';init();', context);
    } else {
        context.define = (name, deps, factory) => {assert.equal(name, 'mod_spatialforum/spatialforum'); factory().init();};
        vm.runInContext(script, context);
    }
    const card = id => root.querySelector('[data-post-id="' + id + '"]');
    const settle = () => new Promise(resolve => setImmediate(resolve));
    const open = id => {card(id).setAttribute('open', ''); card(id).dispatchEvent(new window.Event('toggle'));};
    const close = id => {card(id).removeAttribute('open'); card(id).dispatchEvent(new window.Event('toggle'));};
    function noOverlap() {
        const boxes = [...root.querySelectorAll('[data-post-id]')].map(el => ({id: el.dataset.postId,
            x: parseFloat(el.style.left), y: parseFloat(el.style.top), w: parseFloat(el.style.width),
            h: el.getBoundingClientRect().height}));
        for (const a of boxes) {
            const canvasWidth = parseFloat(root.querySelector('[data-region="stage"]').style.width);
            assert.ok(a.x >= 0 && a.x + a.w <= canvasWidth + 1, a.id + ' outside scroll canvas');
            for (const b of boxes) {
                if (a.id >= b.id) continue;
                assert.ok(a.x + a.w <= b.x || b.x + b.w <= a.x || a.y + a.h <= b.y || b.y + b.h <= a.y,
                    a.id + ' overlaps ' + b.id);
            }
        }
    }
    assert.equal(root.querySelectorAll('details').length, 6);
    assert.equal(root.querySelectorAll('.spatialforum-preview').length, 6);
    assert.equal(card(10).querySelectorAll('summary time').length, 1);
    assert.equal(card(12).querySelectorAll('summary time').length, 2);
    assert.equal(card(12).querySelectorAll('.spatialforum-dates time').length, 2);
    assert.equal(card(12).querySelector('.spatialforum-dates time').getAttribute('datetime'), '2026-10-25T12:00:00+00:00');
    assert.equal(requests.length, 0, 'Loading previews must not mark posts read');
    assert.ok(root.classList.contains('is-map'));
    assert.equal(parseFloat(card(10).style.width), width * 0.5 - 48);
    assert.equal(parseFloat(card(11).style.width), Math.max(240, width * 0.25) - 48);
    const wire = root.querySelector('[data-wire-post="12"]');
    assert.equal(wire.getAttribute('stroke'), card(12).style.getPropertyValue('--post-colour'));
    assert.equal(card(12).style.getPropertyValue('--post-colour'), card(11).style.getPropertyValue('--post-colour'));
    for (const form of root.querySelectorAll('.spatialforum-moderation form')) {
        assert.equal(form.getAttribute('method'), 'post');
        assert.equal(form.querySelector('[name="id"]').getAttribute('value'), '42');
        assert.equal(form.querySelector('[name="sesskey"]').getAttribute('value'), 'token');
    }
    assert.equal(root.querySelectorAll('.spatialforum-moderation a').length, 0);
    if (mode === 'neutral') {
        assert.equal(card(11).style.getPropertyValue('--post-colour'), '#dfe7e2');
        assert.equal(card(13).style.getPropertyValue('--post-colour'), '#dfe7e2');
        return;
    }
    assert.notEqual(card(13).style.getPropertyValue('--post-colour'), card(11).style.getPropertyValue('--post-colour'));
    noOverlap();
    if (cyclic) return;
    if (mode.startsWith('unread')) {
        failNext12 = false;
        const filter = root.querySelector('[data-region="only-unread"]');
        filter.checked = true;
        filter.dispatchEvent(new window.Event('change'));
        root.querySelector('[data-next-unread]').click();
        await settle();
        assert.equal(card(11).hidden, false, 'The active post remains visible while being read.');
        root.querySelector('[data-next-unread]').click();
        await settle();
        if (releaseRead11) {
            releaseRead11();
            await settle();
        }
        assert.equal(card(11).hidden, true, 'The previously read, closed post leaves Unread only.');
        assert.equal(card(12).hidden, false);
        root.querySelector('[data-next-unread]').click();
        await settle();
        assert.equal(card(12).hidden, true);
        close(14);
        assert.equal(root.querySelector('[data-region="no-results"]').hidden, false);
        assert.equal(root.querySelector('[data-next-unread]').disabled, true);
        return;
    }
    root.querySelector('[data-next-unread]').click();
    await settle();
    assert.equal(requests.length, 1);
    assert.equal(card(11).dataset.unread, '0');
    assert.equal(card(12).dataset.unread, '1');
    assert.equal(root.querySelector('[data-region="unread-count"]').textContent, '2');
    assert.equal(requests[0].options.method, 'POST');
    assert.equal(requests[0].params.get('id'), '42');
    assert.equal(requests[0].params.get('sesskey'), 'token');
    assert.equal(requests[0].params.get('postid'), '11');
    assert.equal(card(11).parentElement, root.querySelector('.spatialforum-posts'));
    assert.equal(root.querySelector('[data-region="viewport"]').hidden, false);
    assert.equal(root.querySelectorAll('[data-wire-post]').length, 4, 'All thread connections remain rendered');
    assert.equal(cardsVisible().length, 6, 'Opening a post keeps the complete overview');
    assert.ok(parseFloat(card(11).style.width) >= 320, 'Opened replies have a readable column width');
    assert.ok(parseFloat(card(10).style.width) + parseFloat(card(11).style.width) + 96 <= width,
        'The parent column and expanded reply fit side by side on desktop');
    noOverlap();
    assert.equal(new URL(location.href).searchParams.get('postid'), '11');
    close(11); open(11); await settle();
    assert.equal(requests.length, 1, 'Already saved read state is idempotent');
    open(12); await settle();
    noOverlap();
    assert.equal(root.querySelectorAll('details[open]').length, 1);
    assert.equal(card(12).dataset.unread, '1', 'Failed persistence must not show Read');
    assert.equal(root.querySelector('[data-region="read-error"]').hidden, false);
    open(14); await settle();
    assert.equal(root.querySelector('[data-region="read-error"]').hidden, false, 'Unrelated success hides no failure');
    open(12); await settle();
    assert.equal(card(12).dataset.unread, '0');
    assert.equal(root.querySelector('[data-region="unread-count"]').textContent, '0');
    assert.equal(root.querySelector('[data-region="read-error"]').hidden, true);
    assert.deepEqual([...root.querySelectorAll('[data-region="route"] [data-open-post]')].map(a => a.dataset.openPost),
        ['10', '11', '12']);
    const esc = new window.Event('keydown', {bubbles: true, cancelable: true});
    esc.key = 'Escape'; card(12).dispatchEvent(esc);
    assert.equal(root.querySelectorAll('details[open]').length, 0);
    assert.equal(observedFocus, card(12).querySelector('summary'));
    for (const post of posts) {open(post.id); await settle();}
    close(15);
    width = 360;
    observers.forEach(cb => cb([{target: root.querySelector('[data-region="viewport"]')}]));
    assert.ok(root.classList.contains('is-list'), 'Narrow viewports default to a readable list');
    assert.equal(card(12).dataset.unread, '0');
    open(11); await settle();
    assert.equal(root.querySelectorAll('details[open]').length, 1);
    card(11).querySelector('[data-close]').click();
    assert.equal(root.querySelectorAll('details[open]').length, 0);
    assert.equal(card(11).parentElement, root.querySelector('.spatialforum-posts'));
    assert.equal(new URL(location.href).searchParams.has('postid'), false);
    const search = root.querySelector('[data-region="search"]');
    search.value = 'Full content for post 12';
    search.dispatchEvent(new window.Event('input'));
    assert.equal(cardsVisible().length, 1);
    assert.equal(cardsVisible()[0].dataset.postId, '12');
    search.value = 'not present'; search.dispatchEvent(new window.Event('input'));
    assert.equal(root.querySelector('[data-region="no-results"]').hidden, false);
    root.querySelector('[data-view="map"]').click();
    assert.equal(cardsVisible().length, 6);
    assert.ok(root.classList.contains('is-map'), 'Explicit Map overrides the mobile default');
    open(12); await settle();
    location.href = 'https://example.test/mod/spatialforum/view.php?id=42&sfview=list#post-10';
    location.hash = '#post-10'; events.popstate();
    assert.equal(root.querySelector('.spatialforum-posts details[open]').dataset.postId, '10');
    assert.ok(root.classList.contains('is-list'));
    function cardsVisible() {return [...root.querySelectorAll('[data-post-id]')].filter(el => !el.hidden);}
    return {root};
}

(async() => {
    for (const code of [source, built]) {
        await exercise(code);
        await exercise(code, true);
        await exercise(code, false, 'neutral');
        await exercise(code, false, 'unread');
        await exercise(code, false, 'unread-late');
    }
    const css = fs.readFileSync(path.join(plugin, 'styles.css'), 'utf8');
    assert.ok(css.includes('-webkit-line-clamp: 2'));
    assert.ok(css.includes('.mod-spatialforum.is-list'));
    assert.ok(!css.includes('perspective('));
    const read = fs.readFileSync(path.join(plugin, 'read.php'), 'utf8');
    assert.ok(read.includes("'POST'"));
    assert.ok(read.includes('require_sesskey()'));
    assert.ok(read.includes("'spatialforumid' => $cm->instance"));
    assert.ok(read.includes('isguestuser()'));
    console.log('PASS: source/build, real template, unfolding layout, isolated read writes, failures/retry, route, Escape and cyclic imports');
})().catch(error => {console.error(error); process.exitCode = 1;});
