"""Deploy an explicit allowlist using certificate-verified, encrypted FTPS.
No mirror/delete operation is used. Remote config.php and database data are untouched.
The FTP user must be restricted in cPanel to this application's document root.
"""
import ftplib
import os
from pathlib import Path
import ssl
import uuid

ROOT = Path('dist')
ALLOWED = {'index.html', 'api.php', '.htaccess', '.user.ini'}


def deploy_files(root):
    files = []
    for path in sorted(root.rglob('*')):
        if path.is_symlink():
            raise ValueError('Symlinks are not allowed in deployment artifacts.')
        if not path.is_file():
            continue
        rel = path.relative_to(root).as_posix()
        if rel not in ALLOWED and not (rel.startswith('assets/') and path.suffix in {'.js', '.css'}):
            raise ValueError(f'Unexpected deployment file: {rel}')
        files.append((path, rel))
    if not ALLOWED.issubset({rel for _, rel in files}):
        raise ValueError('Required deployment files are missing.')
    # Publish the HTML entrypoint last, after its hashed assets exist.
    return sorted(files, key=lambda pair: pair[1] == 'index.html')


def upload(ftp, files):
    ftp.cwd('/')
    # Refuse to populate a random FTP root or an unconfigured deployment.
    existing = {Path(name).name for name in ftp.nlst()}
    if not {'config.php', 'api.php', 'index.html'}.issubset(existing):
        raise RuntimeError('FTP account root must be the existing configured Loop folder.')
    if 'assets' not in existing:
        ftp.mkd('assets')
    for path, rel in files:
        temporary = rel + '.deploy-' + uuid.uuid4().hex + '.tmp'
        try:
            with path.open('rb') as source:
                ftp.storbinary('STOR ' + temporary, source)
            ftp.rename(temporary, rel)
        except Exception:
            try:
                ftp.delete(temporary)
            except ftplib.all_errors:
                pass
            raise
    print(f'Deployed {len(files)} app files. Server configuration and database were preserved.')


def main():
    files = deploy_files(ROOT)
    context = ssl.create_default_context()
    with ftplib.FTP_TLS(context=context, timeout=60) as ftp:
        ftp.connect(os.environ['FTP_HOST'], 21)
        ftp.login(os.environ['FTP_USERNAME'], os.environ['FTP_PASSWORD'])
        ftp.prot_p()
        ftp.set_pasv(True)
        upload(ftp, files)


if __name__ == '__main__':
    main()
