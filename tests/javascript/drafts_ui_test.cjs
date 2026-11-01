/**
 * Recover tab drafts after uncertain publication and isolate actors and reply contexts.
 * @copyright 2026 Anders Christian Sloth
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const {parseHTML} = require('linkedom');
const plugin = path.join(__dirname, '../..');
for (const built of [false, true]) {
    const storage = new Map();
    const scope = '101:31:reply:7:14';
    const key = 'mod_spatialforum:draft:' + scope;
    const mount = (options = {}, tiny = false, unavailable = false) => {
        const {document, window} = parseHTML('<form><input name="subject" value="Re: Example">' +
            '<input name="submissionkey" value="new-key"><input name="message_editor[itemid]" value="0">' +
            '<input type="checkbox" name="showparent"><textarea name="message_editor[text]"></textarea></form>');
        let interval;
        let body = '';
        const editor = tiny ? {getContent: () => body, setContent: value => {body = value;}, focus: () => {}} : null;
        const adapter = {getInstanceForElement: () => editor};
        const browser = {sessionStorage: {
            getItem: key => storage.get(key),
            removeItem: key => storage.delete(key),
            setItem: (key, value) => {if (unavailable) throw new Error('Blocked'); storage.set(key, value);}
        }, setInterval: fn => {interval = fn;}, clearInterval: () => {}, addEventListener: () => {}};
        const context = vm.createContext({document, window: browser, Tiny: adapter});
        const config = {scope, restore: true, ready: 'Ready', saved: 'Saved', available: 'Available',
            restoreLabel: 'Restore', discardLabel: 'Discard', unavailable: 'Unavailable', ...options};
        const code = fs.readFileSync(path.join(plugin, built ? 'amd/build/drafts.min.js' : 'amd/src/drafts.js'), 'utf8');
        if (built) {
            context.define = (name, deps, factory) => {
                assert.equal(name, 'mod_spatialforum/drafts');
                assert.equal(deps[0], 'editor_tiny/editor');
                factory(adapter).init(config);
            };
            vm.runInContext(code, context);
        } else {
            context.options = config;
            vm.runInContext(code.replace("import * as Tiny from 'editor_tiny/editor';", '')
                .replace('export const init', 'const init') + ';init(options);', context);
        }
        return {document, window, interval: () => interval?.(), setBody: value => {body = value;}, getBody: () => body};
    };
    let page = mount({}, true);
    page.interval();
    assert.equal(storage.size, 0, 'Opening an untouched form does not create a draft');
    page.document.querySelector('[name="subject"]').value = 'English QA draft';
    page.document.querySelector('[name="submissionkey"]').value = 'original-key';
    page.setBody('<p>A long answer before the connection failed.</p>');
    page.interval();
    assert.equal(JSON.parse(storage.get(key)).body, page.getBody());
    const submit = new page.window.Event('submit');
    page.document.querySelector('form').dispatchEvent(submit);
    assert.ok(storage.has(key), 'Submission keeps the draft until success is confirmed');
    const saved = storage.get(key);
    const other = mount({scope: '102:31:reply:7:14'});
    assert.equal(other.document.querySelector('button'), null, 'Another actor cannot restore this draft');
    page = mount({}, true);
    assert.equal(page.getBody(), '', 'Recovery requires the participant to choose Restore');
    page.document.querySelector('button').click();
    assert.equal(page.getBody(), JSON.parse(saved).body);
    assert.equal(page.document.querySelector('[name="subject"]').value, 'English QA draft');
    assert.equal(page.document.querySelector('[name="submissionkey"]').value, 'original-key');
    mount({clear: 'different-key'});
    assert.ok(storage.has(key), 'Unrelated publication cannot clear a draft');
    mount({clear: 'original-key'});
    assert.equal(storage.has(key), false, 'Confirmed publication clears its exact draft');
    storage.set(key, saved);
    page = mount();
    page.document.querySelectorAll('button')[1].click();
    page.interval();
    assert.equal(storage.has(key), false, 'Discard does not immediately recreate an unchanged draft');
    storage.set(key, JSON.stringify({...JSON.parse(saved), saved: Date.now() - 86400001}));
    page = mount();
    assert.equal(page.document.querySelector('button'), null);
    assert.equal(storage.has(key), false, 'Expired drafts are removed');
    page = mount({}, false, true);
    page.document.querySelector('textarea').value = 'Text in a restricted browser';
    page.interval();
    assert.equal(page.document.querySelector('[role="status"]').textContent, 'Unavailable');
}
console.log('PASS: tab drafts, uncertain submit, Tiny/plain editor, actor isolation, expiry, confirmed clear and storage errors');
