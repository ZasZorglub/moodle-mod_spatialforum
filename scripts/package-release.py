#!/usr/bin/env python3
"""Build a deterministic English-only Moodle release ZIP from this checkout.

@copyright 2026 Anders Christian Sloth
@license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
"""
from pathlib import Path
import argparse
import hashlib
import re
import subprocess
import zipfile

root = Path(__file__).resolve().parent.parent
parser = argparse.ArgumentParser(description=__doc__)
parser.add_argument('--output', type=Path, required=True)
args = parser.parse_args()
version = (root / 'version.php').read_text()
release = re.search(r"\$plugin->release = '([^']+)'", version).group(1)
files = subprocess.check_output(
    ['git', 'ls-files', '--cached', '-z'], cwd=root
).decode().split('\0')
files = sorted({name for name in files if name and not name.startswith(('.github/', 'lang/da/'))
                and name != '.gitattributes'})
required = {'version.php', 'LICENSE', 'lang/en/spatialforum.php', 'db/install.xml',
            'amd/build/spatialforum.min.js', 'amd/build/excerpts.min.js'}
if not required.issubset(files):
    raise SystemExit('Missing release files: ' + str(required - set(files)))
if any(name.startswith('lang/') and not name.startswith('lang/en/') for name in files):
    raise SystemExit('Only English strings may be shipped')
for name in files:
    if name.endswith(('.php', '.js', '.css', '.mustache', '.svg', '.xml', '.mjs', '.cjs')):
        content = (root / name).read_text()
        if '@copyright' not in content or '@license' not in content:
            raise SystemExit('Missing license/copyright header: ' + name)
args.output.parent.mkdir(parents=True, exist_ok=True)
with zipfile.ZipFile(args.output, 'w', compression=zipfile.ZIP_DEFLATED) as archive:
    directories = {'spatialforum/'}
    for name in files:
        for parent in Path(name).parents:
            if str(parent) != '.':
                directories.add('spatialforum/' + parent.as_posix() + '/')
    for name in sorted(directories):
        info = zipfile.ZipInfo(name, date_time=(2026, 10, 28, 0, 0, 0))
        info.external_attr = (0o40755 << 16) | 0x10
        archive.writestr(info, b'')
    for name in files:
        info = zipfile.ZipInfo('spatialforum/' + name, date_time=(2026, 10, 28, 0, 0, 0))
        info.compress_type = zipfile.ZIP_DEFLATED
        info.external_attr = 0o100644 << 16
        archive.writestr(info, (root / name).read_bytes())
with zipfile.ZipFile(args.output) as archive:
    if not {'spatialforum/', 'spatialforum/lang/en/'}.issubset(archive.namelist()):
        raise SystemExit('Missing explicit directory entries required by Moodle ZIP validation')
    if archive.testzip() is not None:
        raise SystemExit('ZIP integrity failure')
print(f'{release}: {len(files)} files; English only; ZIP integrity OK')
print(hashlib.sha256(args.output.read_bytes()).hexdigest() + '  ' + str(args.output))
