"""Local deploy regression: real rsync, temporary source/destination, simulated SSH.
Never connects to Hostinger or reads production credentials. Run with python3.
"""
import os
from pathlib import Path
import shutil
import subprocess
import tempfile

ROOT = Path(__file__).resolve().parents[1]


def write(path, value):
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(value)


with tempfile.TemporaryDirectory(prefix='filolux-deploy-') as directory:
    temp = Path(directory)
    source = temp / 'source'
    server = temp / 'server/domains/filoluxlaser.com'
    public = server / 'public_html'
    private = server / 'filolux-private'
    write(source / 'scripts/deploy-hostinger.sh', (ROOT / 'scripts/deploy-hostinger.sh').read_text())
    for name in ['app/main.php', 'views/page.php', 'vendor/autoload.php', 'composer.json', 'composer.lock', '.htaccess']:
        write(source / 'filolux-private' / name, 'new-' + name)
    for name in ['assets/app.css', 'index.php', '.htaccess', 'uploads/.htaccess']:
        write(source / 'public' / name, 'new-' + name)
    # These files must never be sent even if present in the local checkout.
    for name in ['config.php', '.env', 'storage/local.log', 'database/initial-data.sql']:
        write(source / 'filolux-private' / name, 'DO-NOT-UPLOAD')
    write(source / 'public/uploads/local-photo.webp', 'DO-NOT-UPLOAD')
    write(source / 'public/router.php', 'DO-NOT-UPLOAD')
    write(public / 'index.php', 'old-index')
    write(private / 'config.php', "<?php return ['url'=>'https://filoluxlaser.com'];")
    write(private / '.env', 'PRODUCTION-ADMIN-HASH')
    write(private / 'storage/php-error.log', 'PRODUCTION-LOG')
    write(private / 'database/keep.sql', 'PRODUCTION-SQL')
    write(public / 'uploads/photo.webp', 'PRODUCTION-PHOTO')
    write(public / 'uploads/.htaccess', 'old-rules')
    write(public / '.well-known/acme-challenge/token', 'KEEP-HTTPS-TOKEN')
    write(public / '.user.ini', 'KEEP-HOSTING-PHP-SETTINGS')
    write(public / 'unrelated.txt', 'KEEP-ROOT-FILE')
    for name in ['app/obsolete.php', 'views/obsolete.php', 'vendor/obsolete.php']:
        write(private / name, 'obsolete')
    write(public / 'assets/obsolete.css', 'obsolete')
    (private / 'config.php').chmod(0o600)
    (private / '.env').chmod(0o600)
    protected = [private / name for name in ['config.php', '.env', 'storage/php-error.log', 'database/keep.sql']]
    protected += [public / name for name in ['uploads/photo.webp', '.well-known/acme-challenge/token', '.user.ini', 'unrelated.txt']]
    snapshots = {p: (p.read_bytes(), p.stat().st_mode) for p in protected}

    # Simulate only the SSH transport; execute the actual remote preflight and
    # rsync server locally, with the same command/argument protocol as SSH.
    shim = temp / 'bin/ssh'
    write(shim, '''#!/usr/bin/env python3
import json, os, sys
from pathlib import Path
args=sys.argv[1:]
if args[:1]==['-F']: args=args[2:]
assert args.pop(0)=='hostinger-filolux'
with Path(os.environ['SSH_TEST_LOG']).open('a') as out: out.write(json.dumps(args)+'\\n')
os.execvp('bash',['bash','-c',' '.join(args)])
''')
    shim.chmod(0o755)
    config = temp / 'ssh_config'
    write(config, '# Local fixture only')
    log = temp / 'transport.log'
    env = {**os.environ, 'PATH': str(shim.parent) + os.pathsep + os.environ['PATH'],
           'FILOLUX_SSH_CONFIG': str(config), 'HOSTINGER_DEPLOY_PATH': str(public), 'SSH_TEST_LOG': str(log)}

    def run(**overrides):
        return subprocess.run(['bash', str(source / 'scripts/deploy-hostinger.sh')],
                              env={**env, **overrides}, text=True, capture_output=True)

    result = run()
    assert result.returncode == 0, result.stdout + result.stderr
    for name in ['app/main.php', 'views/page.php', 'vendor/autoload.php']:
        assert (private / name).read_text() == 'new-' + name
    for name in ['app/obsolete.php', 'views/obsolete.php', 'vendor/obsolete.php']:
        assert not (private / name).exists()
    assert not (public / 'assets/obsolete.css').exists()
    assert (public / 'assets/app.css').read_text() == 'new-assets/app.css'
    assert (public / 'index.php').read_text() == 'new-index.php'
    assert (public / 'uploads/.htaccess').read_text() == 'new-uploads/.htaccess'
    assert not (public / 'router.php').exists()
    assert not (public / 'uploads/local-photo.webp').exists()
    assert not (private / 'storage/local.log').exists()
    assert not (private / 'database/initial-data.sql').exists()
    for path, snapshot in snapshots.items():
        assert (path.read_bytes(), path.stat().st_mode) == snapshot, str(path)

    # Reject invalid paths before any SSH call.
    count = len(log.read_text().splitlines())
    for path in ['/', '/public_html', 'relative/public_html', str(server) + '/../other/public_html', str(server) + '//public_html']:
        assert run(HOSTINGER_DEPLOY_PATH=path).returncode != 0, path
    assert len(log.read_text().splitlines()) == count

    # A different project's config must stop before rsync modifies files.
    write(private / 'config.php', "<?php return ['url'=>'https://other-project.example'];")
    write(private / 'app/main.php', 'untouched')
    result = run()
    assert result.returncode != 0 and 'does not belong' in result.stderr
    assert (private / 'app/main.php').read_text() == 'untouched'

    write(private / 'config.php', "<?php return ['url'=>'https://filoluxlaser.com'];")
    shutil.rmtree(private / 'app')
    outside = temp / 'outside'
    write(outside / 'keep.php', 'KEEP-OUTSIDE')
    (private / 'app').symlink_to(outside, target_is_directory=True)
    assert run().returncode != 0
    assert (outside / 'keep.php').read_text() == 'KEEP-OUTSIDE'

print('PASS: rsync real sobre carpetas temporales; código actualizado, obsoletos eliminados, configuración/fotos/logs/permisos conservados; rutas inválidas, otro dominio y enlaces rechazados. SSH simulado; sin conexión a Hostinger.')
