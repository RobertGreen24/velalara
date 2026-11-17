import importlib.machinery
import importlib.util
import os
import tempfile
import types
import unittest
from pathlib import Path
from unittest.mock import patch

loader = importlib.machinery.SourceFileLoader('helper', str(Path(__file__).resolve().parents[2] / 'deploy/velalara-helper'))
spec = importlib.util.spec_from_loader(loader.name, loader)
h = importlib.util.module_from_spec(spec)
loader.exec_module(h)

class HelperTest(unittest.TestCase):
    def test_validation(self):
        self.assertEqual(h.username('vl_123456789abc'), 'vl_123456789abc')
        for name in ['root', 'vl_123456789ab;', 'vl_ABCDEFGHIJKL']:
            with self.assertRaises(ValueError): h.username(name)
        for host in ['example.com;id', '-a.example', 'example.com\n', '*.example.com']:
            with self.assertRaises(ValueError): h.domain(host)
        self.assertEqual(h.domain('www.example.com'), 'www.example.com')

    def test_nginx_template_includes_per_user_socket_and_acme(self):
        text = h.nginx_config('vl_123456789abc', 'example.com', True)
        self.assertIn('/run/php/vl_123456789abc.sock', text)
        self.assertIn('/etc/letsencrypt/live/example.com/fullchain.pem', text)
        self.assertIn('/var/lib/velalara/acme', text)
        self.assertIn('disable_symlinks on;', text)

    def test_file_boundary_and_symlinks(self):
        with tempfile.TemporaryDirectory() as d:
            root = Path(d) / 'web'; root.mkdir()
            secret = Path(d) / 'secret'; secret.write_text('secret')
            (root / 'link').symlink_to(secret)
            (root / 'dirlink').symlink_to(Path(d), target_is_directory=True)
            original = os.open
            def open_safe(path, *args, **kwargs):
                if path == '/home/vl_123456789abc/public_html': path = str(root)
                return original(path, *args, **kwargs)
            with patch.object(h, 'account', return_value=types.SimpleNamespace(pw_uid=os.getuid(), pw_gid=os.getgid())), patch.object(h.os, 'setgroups'), patch.object(h.os, 'setgid'), patch.object(h.os, 'setuid'), patch.object(h.os, 'open', side_effect=open_safe):
                for path in ['../secret', '/etc/passwd', 'x/../../secret']:
                    with self.assertRaises(ValueError): h.file_op('vl_123456789abc', 'read', path)
                with self.assertRaises(OSError): h.file_op('vl_123456789abc', 'read', 'link')
                with self.assertRaises(OSError): h.file_op('vl_123456789abc', 'read', 'dirlink/secret')
                h.file_op('vl_123456789abc', 'write', 'link', 'c2FmZQ==')
                self.assertEqual(secret.read_text(), 'secret')
                self.assertEqual((root / 'link').read_text(), 'safe')
                self.assertEqual(h.file_op('vl_123456789abc', 'read', 'link')['content'], 'c2FmZQ==')
                h.file_op('vl_123456789abc', 'delete', 'link')
                self.assertFalse((root / 'link').exists())

if __name__ == '__main__': unittest.main()
