#!/usr/bin/env python3
"""Prepare derived catalogs and build a validated CASAWP release ZIP."""

import argparse
import gettext
import io
from pathlib import Path
import re
import stat
import subprocess
import zipfile


ROOT = Path(__file__).resolve().parents[3]
STANDARDS = 'vendor/casasoft/casamodules/src/CasasoftStandards/language/'
ALIASES = {
    'languages/casawp-de_CH.mo': 'languages/casawp-de_DE.mo',
    'languages/casawp-de_CH_informal.mo': 'languages/casawp-de_DE.mo',
    STANDARDS + 'de_AT.mo': STANDARDS + 'de.mo',
    STANDARDS + 'de_CH.mo': STANDARDS + 'de.mo',
    STANDARDS + 'de_CH_informal.mo': STANDARDS + 'de.mo',
    STANDARDS + 'en_GB.mo': STANDARDS + 'en.mo',
    STANDARDS + 'es_ES.mo': STANDARDS + 'es.mo',
    STANDARDS + 'fr.mo': STANDARDS + 'fr_FR.mo',
    STANDARDS + 'fr_CH.mo': STANDARDS + 'fr_FR.mo',
    STANDARDS + 'it_CH.mo': STANDARDS + 'it.mo',
}
EXCLUDED = ('distribution/', 'tests/', 'skills/', '.github/')


def prepare_catalogs():
    # Validate every source before replacing any symlinks. Never write through
    # a symlink: that could overwrite its canonical translation source.
    contents = {name: (ROOT / source).read_bytes() for name, source in ALIASES.items()}
    for data in contents.values():
        gettext.GNUTranslations(io.BytesIO(data))
    for name, data in contents.items():
        target = ROOT / name
        if target.is_symlink():
            target.unlink()
        if not target.exists() or target.read_bytes() != data:
            target.write_bytes(data)


def validate_metadata():
    plugin = (ROOT / 'casawp.php').read_text()
    readme = (ROOT / 'README.txt').read_text()
    updater = (ROOT / 'distribution/wp.casasoft.com/casawp/update.php').read_text()
    version = re.search(r'Version: ([\d.]+)', plugin).group(1)
    versions = [
        re.search(r"\$plugin_current_version = '([^']+)'", plugin).group(1),
        re.search(r'Stable tag: ([\d.]+)', readme).group(1),
        re.search(r"\$obj->new_version = '([^']+)'", updater).group(1),
    ]
    if any(value != version for value in versions) or f'= {version} =' not in readme:
        raise ValueError('Plugin, updater, stable tag, and changelog versions must match')
    tested = re.search(r'Tested up to: ([\d.]+)', readme).group(1)
    if tested != re.search(r"\$obj->tested = '([^']+)'", updater).group(1):
        raise ValueError('WordPress compatibility declarations must match')
    return version


def build(output):
    version = validate_metadata()
    prepare_catalogs()
    tracked = subprocess.check_output(['git', 'ls-files', '-z'], cwd=ROOT).decode().split('\0')
    # Explicitly include newly generated aliases even before they are staged.
    names = sorted(set(tracked) | set(ALIASES))
    files = {}
    catalogs = 0
    for name in names:
        if not name or name.startswith(EXCLUDED):
            continue
        path = ROOT / name
        if path.resolve() == output:
            raise ValueError('The output ZIP must not be a tracked plugin file')
        if path.is_symlink():
            raise ValueError(f'Release files must not be symlinks: {name}')
        data = path.read_bytes()
        if name.endswith('.mo'):
            gettext.GNUTranslations(io.BytesIO(data))
            catalogs += 1
        files['casawp/' + name] = (data, path.stat().st_mode & 0o777)
    for required in ('casawp.php', 'vendor/autoload.php', 'action-scheduler/action-scheduler.php'):
        if 'casawp/' + required not in files:
            raise ValueError(f'Missing required plugin file: {required}')
    temporary = output.with_name(output.name + '.tmp')
    try:
        with zipfile.ZipFile(temporary, 'w', zipfile.ZIP_DEFLATED) as release:
            for name, (data, permissions) in files.items():
                info = zipfile.ZipInfo(name)
                info.create_system = 3
                info.external_attr = (stat.S_IFREG | permissions) << 16
                release.writestr(info, data, compress_type=zipfile.ZIP_DEFLATED)
        with zipfile.ZipFile(temporary) as release:
            if release.testzip() is not None:
                raise ValueError('ZIP integrity check failed')
        temporary.replace(output)
    finally:
        if temporary.exists():
            temporary.unlink()
    print(f'CASAWP {version}: {catalogs} valid MO catalogs, {len(files)} files')
    print(f'Release ZIP: {output}')


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('output', type=Path, help='Output ZIP path, e.g. /tmp/casawp-3.5.1.zip')
    build(parser.parse_args().output.resolve())
