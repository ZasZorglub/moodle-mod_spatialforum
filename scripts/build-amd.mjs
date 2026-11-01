/**
 * Samtalespor spatial discussion activity.
 *
 * @package    mod_spatialforum
 * @copyright  2026 Anders Christian Sloth
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {readFileSync, writeFileSync} from 'node:fs';
import {fileURLToPath} from 'node:url';
import {Script} from 'node:vm';

// Keep shipped AMD wrappers reproducible without a Moodle checkout.
for (const name of ['spatialforum', 'excerpts', 'drafts']) {
    const source = readFileSync(new URL(`../amd/src/${name}.js`, import.meta.url), 'utf8');
    if (!source.includes('export const init =')) throw new Error(`Unexpected module export: ${name}`);
    const dependencies = name === 'drafts' ? '["editor_tiny/editor"],function(Tiny)' : name === 'spatialforum' ?
        '["core/fragment","core/templates"],function(Fragment,Templates)' : '[],function()';
    const code = `define("mod_spatialforum/${name}",${dependencies}{\n` +
        source.replace(/^import \* as \w+ from '[^']+';\n/gm, '').replace('export const init =', 'const init =') +
        '\nreturn {init};\n});\n';
    new Script(code, {filename: name});
    const target = fileURLToPath(new URL(`../amd/build/${name}.min.js`, import.meta.url));
    if (process.argv.includes('--check')) {
        if (readFileSync(target, 'utf8') !== code) throw new Error(`Stale AMD build: ${name}`);
    } else {
        writeFileSync(target, code);
    }
}
console.log('Verified reproducible Moodle AMD modules.');
