import json
import os
from pathlib import Path
import tempfile
import unittest

from publisher import Publisher, digest_manifest

OLD = "a" * 40
NEW = "b" * 40


class Interrupted(BaseException):
    pass


class PublisherTest(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory()
        self.addCleanup(self.temp.cleanup)
        self.root = Path(self.temp.name)
        self.releases = self.root / "releases"
        self.state = self.root / "state"
        self.config = self.root / "config"
        for directory in (self.releases, self.state, self.config):
            directory.mkdir(mode=0o700)
        for sha in (OLD, NEW):
            release = self.releases / sha
            release.mkdir(mode=0o700)
            (release / "index.php").write_text(sha)
        self.auth = self.config / "publication.json"
        self.data = {
            "enabled": True, "environment": "homologacao",
            "old": OLD, "new": NEW,
            "digests": {sha: digest_manifest(self.releases / sha, os.getuid())
                        for sha in (OLD, NEW)},
        }
        self.save_auth()
        self.publisher = self.reopen()
        self.publisher.switch(OLD)

    def save_auth(self):
        self.auth.write_text(json.dumps(self.data))
        self.auth.chmod(0o600)

    def reopen(self):
        return Publisher(self.releases, self.state, self.auth, owner=os.getuid())

    def test_success(self):
        self.assertEqual(self.publisher.publish(lambda _: True), "aplicado")
        self.assertEqual(self.publisher.current(), NEW)
        self.assertFalse(self.publisher.pending.exists())

    def test_failed_http_rolls_back(self):
        self.assertEqual(self.publisher.publish(lambda sha: sha == OLD), "revertido")
        self.assertEqual(self.publisher.current(), OLD)
        self.assertFalse(self.publisher.pending.exists())

    def test_recovery_after_interruption(self):
        def interrupt(phase):
            if phase == "switched":
                raise Interrupted()
        with self.assertRaises(Interrupted):
            self.publisher.publish(lambda _: True, interrupt)
        reopened = self.reopen()
        self.assertEqual(reopened.current(), NEW)
        self.assertEqual(reopened.recover(lambda _: True), "revertido")
        self.assertEqual(reopened.current(), OLD)

    def test_failed_recovery_keeps_journal(self):
        with self.assertRaisesRegex(RuntimeError, "recuperacao_http_falhou"):
            self.publisher.publish(
                self.fail_after_first()
            )
        self.assertEqual(self.publisher.current(), OLD)
        self.assertTrue(self.publisher.pending.exists())

    @staticmethod
    def fail_after_first():
        values = iter([True, False, False])
        return lambda _: next(values)

    def test_disabled(self):
        self.data["enabled"] = False
        self.save_auth()
        with self.assertRaisesRegex(RuntimeError, "publicacao_desabilitada"):
            self.publisher.publish(lambda _: True)
        self.assertEqual(self.publisher.current(), OLD)

    def test_corrupt_candidate(self):
        (self.releases / NEW / "index.php").write_text("changed")
        with self.assertRaisesRegex(RuntimeError, "integridade_release"):
            self.publisher.publish(lambda _: True)
        self.assertEqual(self.publisher.current(), OLD)

    def test_symlink_rejected(self):
        (self.releases / NEW / "link").symlink_to(self.auth)
        with self.assertRaisesRegex(RuntimeError, "permissao_ou_tipo"):
            self.publisher.publish(lambda _: True)

    def test_writable_release_rejected(self):
        (self.releases / NEW / "index.php").chmod(0o666)
        with self.assertRaisesRegex(RuntimeError, "permissao_ou_tipo"):
            self.publisher.publish(lambda _: True)

    def test_unhealthy_old_blocks(self):
        with self.assertRaisesRegex(RuntimeError, "versao_anterior_indisponivel"):
            self.publisher.publish(lambda _: False)
        self.assertFalse(self.publisher.pending.exists())

    def test_lock_blocks(self):
        with self.publisher.lock():
            with self.assertRaises(BlockingIOError):
                self.publisher.publish(lambda _: True)


if __name__ == "__main__":
    unittest.main()
