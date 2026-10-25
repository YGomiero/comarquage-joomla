"""Build deterministic Joomla ZIPs and the checksum-pinned update feed (Python 3)."""
from pathlib import Path
import hashlib, io, zipfile, xml.etree.ElementTree as ET

ROOT = Path(__file__).resolve().parents[1]
OUT = ROOT / 'dist'
OUT.mkdir(exist_ok=True)
VERSION = ET.parse(ROOT / 'pkg_comarquage.xml').getroot().findtext('version')

def archive(files):
    stream = io.BytesIO()
    with zipfile.ZipFile(stream, 'w', zipfile.ZIP_DEFLATED, compresslevel=9) as z:
        for name, data in sorted(files.items()):
            entry = zipfile.ZipInfo(name, (2026, 1, 1, 0, 0, 0))
            entry.compress_type = zipfile.ZIP_DEFLATED
            entry.external_attr = 0o644 << 16
            z.writestr(entry, data)
    return stream.getvalue()

def source_bytes(path):
    data = path.read_bytes()
    # Git uses LF; keep local Windows and Linux CI packages byte-identical.
    if path.suffix.lower() in {'.php','.xml','.ini','.sql','.json','.js','.css','.svg','.md','.txt','.html'}:
        data = data.replace(b'\r\n', b'\n')
    return data

def tree(path):
    return {p.relative_to(path).as_posix(): source_bytes(p) for p in path.rglob('*') if p.is_file()}

component = tree(ROOT / 'component')
component['comarquage.xml'] = component.pop('admin/comarquage.xml')
package = {p: source_bytes(ROOT/p) for p in ['pkg_comarquage.xml', 'script.php', 'LICENSE.txt']}
package.update({'language/'+k: v for k,v in tree(ROOT/'language').items()})
package['packages/com_comarquage.zip'] = archive(component)
for group,element in [('task','comarquageimport'),('finder','comarquage')]:
    package[f'packages/plg_{group}_{element}.zip'] = archive(tree(ROOT/'plugins'/group/element))
name = f'pkg_comarquage-{VERSION}.zip'
blob = archive(package)
(OUT/name).write_bytes(blob)
sha = hashlib.sha256(blob).hexdigest()
feed = f'''<?xml version="1.0" encoding="utf-8"?>
<updates><update>
 <name>Distro</name><description>Service-Public / DILA Open Data for Joomla</description>
 <element>pkg_comarquage</element><type>package</type><version>{VERSION}</version>
 <infourl title="Distro">https://github.com/YGomiero/distro-joomla/releases/tag/{VERSION}</infourl>
 <downloads><downloadurl type="full" format="zip">https://github.com/YGomiero/distro-joomla/releases/download/{VERSION}/{name}</downloadurl></downloads>
 <tags><tag>stable</tag></tags><targetplatform name="joomla" version="6\\.[0-9]+"/>
 <php_minimum>8.3</php_minimum><sha256>{sha}</sha256>
</update></updates>
'''
(OUT/'update.xml').write_bytes(feed.encode('utf-8'))
(OUT/'SHA256SUMS').write_bytes(f'{sha}  {name}\n{hashlib.sha256(feed.encode()).hexdigest()}  update.xml\n'.encode('ascii'))
print(name, len(blob), sha)
