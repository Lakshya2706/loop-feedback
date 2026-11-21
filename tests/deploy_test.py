import importlib.util
from pathlib import Path
import tempfile
import unittest

spec = importlib.util.spec_from_file_location('deploy', 'scripts/deploy-ftps.py')
module = importlib.util.module_from_spec(spec)
spec.loader.exec_module(module)


class FakeFTP:
    def __init__(self, names):
        self.names = names
        self.writes = []
        self.renames = []
        self.deletes = []
    def cwd(self, path):
        assert path == '/'
    def nlst(self):
        return self.names
    def mkd(self, path):
        self.names.append(path)
    def storbinary(self, command, source):
        self.writes.append((command, source.read()))
    def rename(self, old, new):
        self.renames.append((old, new))
    def delete(self, path):
        self.deletes.append(path)


class DeploymentTests(unittest.TestCase):
    def artifact(self, root):
        for name in module.ALLOWED:
            (root / name).write_text('app content')
        (root / 'assets').mkdir()
        (root / 'assets/app.js').write_text('script')
    def test_only_app_files_upload_and_index_is_last(self):
        with tempfile.TemporaryDirectory() as folder:
            root = Path(folder)
            self.artifact(root)
            files = module.deploy_files(root)
            ftp = FakeFTP(['config.php', 'api.php', 'index.html', 'backup.sql'])
            module.upload(ftp, files)
            targets = [new for _, new in ftp.renames]
            self.assertEqual(targets[-1], 'index.html')
            self.assertNotIn('config.php', targets)
            self.assertNotIn('backup.sql', targets)
            self.assertEqual(ftp.deletes, [])
    def test_wrong_account_root_is_rejected_before_writes(self):
        ftp = FakeFTP(['public_html'])
        with self.assertRaises(RuntimeError):
            module.upload(ftp, [])
        self.assertEqual(ftp.writes, [])
    def test_secrets_and_sql_are_rejected(self):
        for name in ['config.php', 'smtp-config.php', 'data.sql', 'assets/data.sql']:
            with tempfile.TemporaryDirectory() as folder:
                root = Path(folder)
                self.artifact(root)
                (root / name).write_text('must not publish')
                with self.assertRaises(ValueError):
                    module.deploy_files(root)


if __name__ == '__main__':
    unittest.main()
