/**
 * Samtalespor spatial discussion activity.
 *
 * @package    mod_spatialforum
 * @copyright  2026 Anders Christian Sloth
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Selection and composer regressions against the actual template and shipped AMD modules.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const {parseHTML} = require('linkedom');
const Mustache = require('mustache');
const plugin = path.join(__dirname, '../..');
const template = fs.readFileSync(path.join(plugin, 'templates/excerpt_composer.mustache'), 'utf8');
for (const built of [false, true]) {
    const filename = built ? 'amd/build/excerpts.min.js' : 'amd/src/excerpts.js';
    const code = fs.readFileSync(path.join(plugin, filename), 'utf8');
    const composer = Mustache.render(template, {str: () => text => text.split(',')[0]});
    const {document, window} = parseHTML('<div class="spatialforum-reply-context"><div class="spatialforum-message">' +
        '<p id="source">Goalkeepers: José</p><blockquote>Someone else</blockquote></div></div>' +
        '<form><label for="id_message_editor">message</label><input name="excerptdraft" value="[]">' + composer + '</form>');
    let focused = null;
    window.HTMLElement.prototype.focus = function() { focused = this; };
    let inside = true, embedded = false, quote = 'Goalkeepers: José';
    const range = {get startContainer() {return inside ? document.getElementById('source') : document;},
        get endContainer() {return inside ? document.getElementById('source') : document;},
        intersectsNode: () => embedded};
    const selection = {rangeCount: 1, isCollapsed: false, getRangeAt: () => range, toString: () => quote};
    const context = vm.createContext({document, window: {getSelection: () => selection}});
    if (built) {
        context.define = (name, deps, factory) => {assert.equal(name, 'mod_spatialforum/excerpts'); factory().init();};
        vm.runInContext(code, context);
    } else {
        vm.runInContext(code.replace('export const init', 'const init') + ';init();', context);
    }
    const select = () => document.dispatchEvent(new window.Event('selectionchange'));
    const add = document.querySelector('[data-add-excerpt]');
    const input = document.querySelector('[name="excerptdraft"]');
    assert.equal(add.disabled, true);
    select(); assert.equal(add.disabled, false);
    assert.equal(add.getAttribute('aria-disabled'), 'false');
    add.click();
    assert.equal(JSON.parse(input.value)[0].quote, quote);
    const comment = document.querySelector('textarea');
    assert.equal(focused, comment, 'New quotes focus their comment field');
    assert.equal(document.querySelector('label[for="id_message_editor"]').textContent, 'closingcomment');
    comment.value = 'My first point'; comment.dispatchEvent(new window.Event('input'));
    assert.equal(JSON.parse(input.value)[0].comment, 'My first point');
    quote = '<img src=x onerror=alert(1)>'; select(); add.click();
    assert.equal(document.querySelectorAll('[data-excerpt-parts] img').length, 0, 'Quote is text, never injected HTML');
    assert.equal(document.querySelectorAll('textarea').length, 2);
    document.querySelector('[data-excerpt-parts] button').click();
    assert.equal(JSON.parse(input.value).length, 1);
    assert.equal(JSON.parse(input.value)[0].quote, quote);
    assert.equal(focused, document.querySelector('textarea'), 'Removing a quote focuses a remaining comment');
    document.querySelector('[data-excerpt-parts] button').click();
    assert.equal(focused, document.querySelector('[data-excerpt-heading]'), 'Removing the last quote focuses its disclosure');
    assert.equal(document.querySelector('label[for="id_message_editor"]').textContent, 'message');
    assert.equal(document.querySelector('[data-excerpt-status]').textContent, 'excerptremoved');
    inside = false; select(); assert.equal(add.disabled, true);
    inside = true; embedded = true; select(); assert.equal(add.disabled, true);
    embedded = false; quote = 'x'.repeat(4001); select(); assert.equal(add.disabled, true);
    quote = 'Valid'; select();
    for (let n = 0; n < 12; n++) add.click();
    assert.equal(JSON.parse(input.value).length, 10);
    assert.ok(document.querySelector('[data-excerpt-status]').textContent);

    const main = fs.readFileSync(path.join(plugin, 'templates/main.mustache'), 'utf8');
    const live = parseHTML(Mustache.render(main, {cmid: 7, posts: [{id: 1, message: '<p>English QA selection</p>',
        canreply: true, replyurl: '/reply'}], str: () => text => text.split(',')[0]},
    {'mod_spatialforum/reply_context': '',
        'mod_spatialforum/post_detail': fs.readFileSync(path.join(plugin, 'templates/post_detail.mustache'), 'utf8')}));
    assert.equal(live.document.querySelector('[data-excerpt-start]'), null, 'Selection reply action is retired');

}
const css = fs.readFileSync(path.join(plugin, 'styles.css'), 'utf8');
assert.ok(css.includes('.spatialforum-excerpt-start button:not(:disabled)'));
assert.ok(css.includes('box-shadow: 0 0 0 3px #26724d33'));
console.log('PASS: excerpt source/build, selected text boundaries, multiple comments, escaping, removal and limits');
