import {readFileSync, writeFileSync} from 'node:fs';
import {fileURLToPath} from 'node:url';
import {Script} from 'node:vm';

// These modules have no dependencies. Keep shipped AMD wrappers reproducible without a Moodle checkout.
for (const name of ['spatialforum', 'excerpts']) {
    const source = readFileSync(new URL(`../amd/src/${name}.js`, import.meta.url), 'utf8');
    if (!source.includes('export const init =')) throw new Error(`Unexpected module export: ${name}`);
    const code = `define("mod_spatialforum/${name}",[],function(){\n` +
        source.replace('export const init =', 'const init =') + '\nreturn {init};\n});\n';
    new Script(code, {filename: name});
    const target = fileURLToPath(new URL(`../amd/build/${name}.min.js`, import.meta.url));
    if (process.argv.includes('--check')) {
        if (readFileSync(target, 'utf8') !== code) throw new Error(`Stale AMD build: ${name}`);
    } else {
        writeFileSync(target, code);
    }
}
console.log('Built both dependency-free Moodle AMD modules.');
