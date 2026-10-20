"""Check the public PHP release in a read-only, credential-free network namespace."""
import argparse
import errno
import hashlib
import json
import os
from pathlib import Path
import re
import shutil
import socket
import subprocess
import sys
import tempfile


ROOT = Path(__file__).resolve().parents[1]
OMIT = {'.git', '.venv', 'vendor', 'node_modules', '__pycache__', '.pytest_cache',
        'dist', 'build', 'coverage', '.cache'}
PUBLIC_TESTS = {'webhook_contract.php': 8, 'release_settings.php': 45,
                'webhook_authentication.php': 28}


def public_files(root):
    for directory, names, files in os.walk(root, followlinks=False):
        for name in names:
            if (Path(directory) / name).is_symlink():
                raise ValueError('Release snapshots do not admit directory symlinks')
        names[:] = [name for name in names if name not in OMIT]
        for name in files:
            path = Path(directory) / name
            if name == '.env' or (name.startswith('.env.') and name != '.env.example'):
                continue
            if name.endswith(('.log', '.sqlite', '.sqlite3', '.db', '.cnt')):
                continue
            if path.is_symlink():
                raise ValueError('Release snapshots do not admit file symlinks')
            yield path


def hashes(root):
    return {str(path.relative_to(root)): hashlib.sha256(path.read_bytes()).hexdigest()
            for path in public_files(root)}


def probe():
    assert not Path('/home').exists() and not Path('/root').exists()
    assert not Path('/work/.git').exists() and not Path('/work/.env').exists()
    assert os.environ['HOME'] == '/tmp'
    assert not any(key.endswith(('_KEY', '_SECRET', '_PASS')) for key in os.environ)
    status = Path('/proc/self/status').read_text()
    capabilities = re.search(r'^CapEff:\s*([0-9a-fA-F]+)$', status, re.MULTILINE)
    assert capabilities and int(capabilities.group(1), 16) == 0, 'Effective capabilities remain'
    assert re.search(r'^NoNewPrivs:\s*1$', status, re.MULTILINE), 'New privileges remain possible'
    mounts = [line.split() for line in Path('/proc/self/mountinfo').read_text().splitlines()]
    for name in ('/work/README.md', '/opt/php', '/opt/node'):
        mountpoint = '/work' if name.startswith('/work/') else name
        assert any(fields[4] == mountpoint and 'ro' in fields[5].split(',') for fields in mounts)
        try:
            descriptor = os.open(name, os.O_WRONLY)
        except OSError as error:
            assert error.errno in (errno.EROFS, errno.EACCES)
        else:
            os.close(descriptor)
            raise AssertionError('Source and runtime must be read-only')
    assert len(Path('/proc/net/route').read_text().strip().splitlines()) == 1
    with socket.socket() as connection:
        connection.settimeout(.5)
        try:
            connection.connect(('1.1.1.1', 443))
        except OSError as error:
            assert error.errno == errno.ENETUNREACH
        else:
            raise AssertionError('Unexpected network egress')
    print('Isolation: source/runtimes read-only, empty home, no credentials, no network, CapEff=0, NoNewPrivs=1.')


def php(arguments, expected=0):
    result = subprocess.run(['/opt/php', '-n', *arguments], stdin=subprocess.DEVNULL,
                            stdout=subprocess.PIPE, stderr=subprocess.STDOUT, timeout=45)
    if result.returncode != expected:
        sys.stdout.buffer.write(result.stdout)
        raise AssertionError('PHP check failed')
    assert len(result.stdout) <= 1_000_000, 'Unexpected PHP output volume'
    return result.stdout.decode('utf-8')


def inner():
    probe()
    files = sorted(Path('/work').rglob('*.php'))
    assert files, 'No PHP source found'
    for path in files:
        php(['-l', str(path)])
    checks = 0
    for name, expected in PUBLIC_TESTS.items():
        output = php(['/work/tests/' + name])
        if name == 'webhook_authentication.php':
            assert output.strip() == f'{expected} webhook authentication checks; 0 failures'
        else:
            report = json.loads(output)
            assert report == {'checks': expected, 'failed': []}
        checks += expected
    php(['/work/bin/create_admin.php'], expected=2)
    legacy = php(['-r', "register_shutdown_function(function(){ echo ' HTTP_STATUS='.http_response_code(); }); require '/work/public/notificacao/index.php';"])
    assert legacy.strip().endswith('HTTP_STATUS=503')
    missing_db = php(['-r', "try { require '/work/src/bootstrap.php'; exit(1); } catch (RuntimeException $error) { if ($error->getMessage()!=='Configure your own database settings before startup.') { throw $error; } echo 'Missing database configuration rejected'; }"])
    assert missing_db.strip() == 'Missing database configuration rejected'
    spec = json.loads(Path('/work/docs/openapi.json').read_text())
    assert len(spec['paths']) == 31 and spec['servers'][0]['url'] == '/'
    for path in Path('/work').rglob('*.sql'):
        assert not re.search(r'^\s*(?:INSERT|REPLACE)\s+INTO\b', path.read_text(), re.I | re.M)
    javascript = subprocess.run(['/opt/node', '--test', '/work/tests/twofa_privacy.test.mjs'],
                                stdin=subprocess.DEVNULL, stdout=subprocess.PIPE,
                                stderr=subprocess.STDOUT, timeout=45)
    output = javascript.stdout.decode('utf-8')
    assert len(javascript.stdout) <= 1_000_000
    if javascript.returncode:
        print(output)
        raise AssertionError('2FA privacy regressions failed')
    for summary in ('# tests 4', '# pass 4', '# fail 0', '# skipped 0'):
        assert summary in output.splitlines()
    print(f'Passed {len(files)} PHP syntax checks, exactly {checks} public PHP checks and 4 JavaScript privacy cases; startup guards and schema/docs validated.')
    print('No database connection, payment, account creation or provider API call was performed.')
    subprocess.run(['/usr/bin/python3', '/work/scripts/run-lab-tests.py', 'stone-open', 'all'], check=True, timeout=180, stdin=subprocess.DEVNULL)


def sandbox(snapshot, php_runtime, node_runtime):
    command = ['bwrap', '--unshare-all', '--unshare-user', '--disable-userns',
               '--die-with-parent', '--new-session', '--cap-drop', 'ALL', '--clearenv']
    for name in ('usr', 'bin', 'sbin', 'lib', 'lib64'):
        path = Path('/') / name
        if path.is_symlink():
            command += ['--symlink', os.readlink(path), '/' + name]
        elif path.exists():
            command += ['--ro-bind', str(path), '/' + name]
    if Path('/etc/ld.so.cache').exists():
        command += ['--ro-bind', '/etc/ld.so.cache', '/etc/ld.so.cache']
    command += ['--proc', '/proc', '--dev', '/dev', '--size', '268435456', '--tmpfs', '/tmp',
                '--ro-bind', str(snapshot), '/work', '--ro-bind', str(php_runtime), '/opt/php',
                '--ro-bind', str(node_runtime), '/opt/node',
                '--chdir', '/work']
    for key, value in {'HOME': '/tmp', 'PATH': '/usr/bin:/bin', 'LANG': 'C.UTF-8',
                       'PYTHONDONTWRITEBYTECODE': '1', 'PYTHONNOUSERSITE': '1',
                       'TMPDIR': '/tmp'}.items():
        command += ['--setenv', key, value]
    return command


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--php', help='Explicit path to the reviewed PHP CLI binary')
    parser.add_argument('--node', help='Explicit path to the reviewed Node.js binary')
    parser.add_argument('--inner', action='store_true', help=argparse.SUPPRESS)
    args = parser.parse_args()
    if args.inner:
        inner()
        return
    if not args.php or not args.node:
        parser.error('--php and --node are required; the checks do not download runtimes')
    runtime = Path(args.php).resolve(strict=True)
    node_runtime = Path(args.node).resolve(strict=True)
    for executable in (runtime, node_runtime):
        if not executable.is_file() or not os.access(executable, os.X_OK):
            parser.error('Runtimes must be executable regular files')
    before = hashes(ROOT)
    runtime_before = hashlib.sha256(runtime.read_bytes()).hexdigest()
    node_before = hashlib.sha256(node_runtime.read_bytes()).hexdigest()
    with tempfile.TemporaryDirectory(prefix='stone-public-check-') as temporary:
        snapshot = Path(temporary) / 'source'
        for source in public_files(ROOT):
            target = snapshot / source.relative_to(ROOT)
            target.parent.mkdir(parents=True, exist_ok=True)
            shutil.copyfile(source, target)
        assert hashes(snapshot) == before
        command = sandbox(snapshot, runtime, node_runtime) + ['--', '/usr/bin/python3',
                    '/work/scripts/checks-offline.py', '--inner']
        result = subprocess.run(command, env={'PATH': '/usr/bin:/bin'}, timeout=600)
        assert hashes(snapshot) == before, 'Snapshot changed during isolated checks'
        assert hashes(ROOT) == before, 'Source changed during isolated checks'
        assert hashlib.sha256(runtime.read_bytes()).hexdigest() == runtime_before, 'Runtime changed'
        assert hashlib.sha256(node_runtime.read_bytes()).hexdigest() == node_before, 'Node runtime changed'
        if result.returncode:
            raise SystemExit(result.returncode)
    print('Source, snapshot, PHP and Node runtime hashes preserved.')


if __name__ == '__main__':
    main()
