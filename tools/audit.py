"""Validate release metadata, language keys and deterministic artifacts."""
from pathlib import Path
import xml.etree.ElementTree as ET
import json,re,sys,hashlib,subprocess
r=Path(__file__).resolve().parents[1]
manifests=[r/'pkg_comarquage.xml',r/'component/admin/comarquage.xml',r/'plugins/task/comarquageimport/comarquageimport.xml',r/'plugins/finder/comarquage/comarquage.xml']
version=ET.parse(manifests[0]).getroot().findtext('version')
for p in manifests:
 root=ET.parse(p).getroot()
 assert root.findtext('version')==version,p
 for key in ['name','author','authorEmail','authorUrl','creationDate','copyright','license','description']:
  assert root.findtext(key),f'{p}: missing {key}'
 assert 'General Public License' in root.findtext('license'),p
assert ET.parse(manifests[0]).getroot().findtext('updateservers/server').endswith('/releases/latest/download/update.xml')
for p in r.rglob('*.xml'): ET.parse(p)
for p in r.rglob('*.json'):json.loads(p.read_text(encoding='utf-8'))
for lang in ['fr-FR','en-GB']:
 defined=set()
 for p in r.rglob('*.ini'):
  if lang not in p.parts:continue
  keys=re.findall(r'^([A-Z][A-Z0-9_]+)\s*=',p.read_text(encoding='utf-8'),re.M)
  assert len(keys)==len(set(keys)),f'Duplicate translation key in {p}'
  defined.update(keys)
 used=set()
 for p in (r/'component').rglob('*.php'):
  used.update(re.findall(r"Text::(?:_|sprintf|script)\('((?:COM_COMARQUAGE_)[A-Z0-9_]+)'",p.read_text(encoding='utf-8')))
 assert not used-defined,f'{lang}: missing translations {sorted(used-defined)}'
subprocess.run([sys.executable,str(r/'tools/build.py')],check=True)
first=(r/'dist'/f'pkg_comarquage-{version}.zip').read_bytes()
subprocess.run([sys.executable,str(r/'tools/build.py')],check=True)
assert first==(r/'dist'/f'pkg_comarquage-{version}.zip').read_bytes(),'Non-deterministic ZIP'
feed=ET.parse(r/'dist/update.xml').getroot().find('update')
assert feed.findtext('sha256')==hashlib.sha256(first).hexdigest()
assert feed.findtext('version')==version
# Joomla records packages with client_id=0; omitting client defaults the feed to administrator.
assert feed.findtext('client')=='site','Package updates must match the installed site client'
for line in (r/'dist/SHA256SUMS').read_text().splitlines():
 digest,name=line.split('  ',1)
 assert hashlib.sha256((r/'dist'/name).read_bytes()).hexdigest()==digest,name
print('Metadata, XML/JSON, FR/EN keys, reproducible ZIP and update checksum passed.')
