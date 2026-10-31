/**
 * Deferred details keep the complete hierarchy and mark a post read only after successful loading.
 * @copyright 2026 Anders Christian Sloth
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const {parseHTML} = require('linkedom');
const Mustache = require('mustache');
const plugin = path.join(__dirname, '../..');
const template = fs.readFileSync(path.join(plugin, 'templates/main.mustache'), 'utf8');

async function exercise(built) {
    const html = Mustache.render(template, {cmid: 42, contextid: 84, initialview: 'list', sesskey: 'session',
        readurl: '/read.php', hasposts: true, readcount: 2, postcount: 2,
        posts: [10, 11].map(id => ({id, parentid: id === 10 ? 0 : 10, subject: 'Post ' + id,
            relationship: id === 10 ? 'Main track' : 'In reply to Post 10', deferred: true,
            unread: true, cantrackread: true, searchtext: id === 10 ? 'Root content' : 'Needle in the full body',
            fullposturl: '/mod/spatialforum/view.php?id=42&postid=' + id})),
        str: () => text => text.split(',')[0].trim()});
    const {document, window} = parseHTML(html);
    let focused;
    window.HTMLElement.prototype.focus = function() {focused = this;};
    window.HTMLElement.prototype.scrollIntoView = function() {};
    window.HTMLElement.prototype.getBoundingClientRect = function() {return {width: 900, height: 180};};
    const requests = [];
    const reads = [];
    const Fragment = {loadFragment: (component, callback, contextid, params) => {
        assert.equal(component, 'mod_spatialforum');
        assert.equal(callback, 'post_detail');
        assert.equal(contextid, 84);
        return {then: (resolve, reject) => requests.push({params, resolve, reject})};
    }};
    const Templates = {replaceNodeContents: async(region, html, js) => {
        assert.equal(js, 'player-init');
        region.innerHTML = html;
    }};
    const location = {href: 'https://example.test/mod/spatialforum/view.php?id=42', hash: ''};
    const context = vm.createContext({document, Fragment, Templates, URL, URLSearchParams,
        performance: {now: () => 0}, requestAnimationFrame: fn => {fn(0); return 1;}, cancelAnimationFrame: () => {},
        window: {location, sessionStorage: {getItem: () => null, setItem: () => {}},
            getComputedStyle: () => ({overflowY: 'visible'}), addEventListener: () => {},
            history: {state: null, pushState: (_, __, value) => {location.href = value;},
                replaceState: (_, __, value) => {location.href = value;}}},
        fetch: async(url, options) => {
            const id = new URLSearchParams(options.body).get('postid');
            reads.push(id);
            return {ok: true, json: async() => ({success: true, postid: Number(id)})};
        }});
    const code = fs.readFileSync(path.join(plugin, built ? 'amd/build/spatialforum.min.js' : 'amd/src/spatialforum.js'), 'utf8');
    if (built) {
        context.define = (name, deps, factory) => factory(Fragment, Templates).init();
        vm.runInContext(code, context);
    } else {
        vm.runInContext(code.replace(/^import \* as \w+ from '[^']+';\n/gm, '')
            .replace('export const init', 'const init') + ';init();', context);
    }
    const card = id => document.querySelector('[data-post-id="' + id + '"]');
    const open = id => {card(id).setAttribute('open', ''); card(id).dispatchEvent(new window.Event('toggle'));};
    const settle = () => new Promise(resolve => setImmediate(resolve));
    assert.equal(requests.length, 0, 'Overview loading does not fetch every body');
    open(10);
    assert.equal(requests.length, 1);
    assert.equal(reads.length, 0, 'A loading placeholder must not be marked read');
    requests[0].reject(new Error('Permission or connection changed'));
    await settle();
    assert.ok(card(10).textContent.includes('postloadfailed'));
    assert.equal(reads.length, 0);
    assert.ok(card(10).querySelector('a.d-block'), 'A full-page fallback remains after a failed request');
    const retry = new window.Event('click', {bubbles: true, cancelable: true});
    retry.button = 0;
    card(10).querySelector('[data-load-detail]').dispatchEvent(retry);
    assert.equal(requests.length, 2);
    card(10).removeAttribute('open');
    card(10).dispatchEvent(new window.Event('toggle'));
    requests[1].resolve('<div class="spatialforum-message">Loaded root</div><button data-close>Close</button>', 'player-init');
    await settle();
    assert.equal(reads.length, 0, 'Completing a request after closing the post does not mark it read');
    open(10);
    await settle();
    assert.deepEqual(reads, ['10']);
    assert.equal(requests.length, 2, 'Reopening a loaded body does not refetch it');
    const search = document.querySelector('[data-region="search"]');
    search.value = 'needle';
    search.dispatchEvent(new window.Event('input'));
    assert.equal(card(10).hidden, true);
    assert.equal(card(11).hidden, false, 'Searching covers the full own text before its details are fetched');
    assert.equal(document.querySelectorAll('[data-post-id]').length, 2, 'Filtering never removes parent metadata');
    open(11);
    requests[2].resolve('<div class="spatialforum-message">Loaded reply</div><button data-close>Close</button>', 'player-init');
    await settle();
    assert.deepEqual(reads, ['10', '11']);
    assert.equal(card(10).hasAttribute('open'), false);
    const summary = card(11).querySelector('summary');
    assert.equal(document.getElementById(summary.getAttribute('aria-describedby')).textContent, 'In reply to Post 10');
    const handled = new window.Event('keydown', {bubbles: true, cancelable: true});
    handled.key = 'Escape'; handled.preventDefault(); card(11).dispatchEvent(handled);
    assert.equal(card(11).hasAttribute('open'), true, 'Already handled Escape is respected');
    const escape = new window.Event('keydown', {bubbles: true, cancelable: true});
    escape.key = 'Escape'; card(11).dispatchEvent(escape);
    assert.equal(card(11).hasAttribute('open'), false);
    assert.equal(focused, summary);
}
(async() => {
    await exercise(false);
    await exercise(true);
    console.log('PASS: deferred source/build, permission/network failure, retry, native fallback, scoped reads, hierarchy, search and focus');
})().catch(error => {console.error(error); process.exit(1);});
