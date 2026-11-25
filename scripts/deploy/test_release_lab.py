import unittest
from release_lab import ReleaseLab


class Interrupted(BaseException):
    pass


class ReleaseLabTest(unittest.TestCase):
    def test_success(self):
        with ReleaseLab() as lab:
            def smoke(active):
                return all("v2-" in (active / name).read_text()
                           for name in ("A.php", "B.php"))
            self.assertEqual(lab.deploy(smoke), "aplicado")
            self.assertEqual(lab.current(), "v2")
            self.assertFalse(lab.journal.exists())
            self.assertEqual(lab.hash(lab.env), lab.env_hash)
            lab.verify("v1")

    def test_smoke_failure(self):
        with ReleaseLab() as lab:
            self.assertEqual(lab.deploy(lambda _: False), "revertido")
            self.assertEqual(lab.current(), "v1")
            lab.verify("v1")
            self.assertFalse(lab.journal.exists())

    def test_interruption(self):
        for phase in ("journal", "switched", "validated"):
            with self.subTest(phase=phase), ReleaseLab() as lab:
                def interrupt(current):
                    if current == phase:
                        raise Interrupted()
                with self.assertRaises(Interrupted):
                    lab.deploy(lambda _: True, interrupt)
                self.assertTrue(lab.journal.exists())
                self.assertEqual(lab.recover(), "revertido")
                self.assertEqual(lab.current(), "v1")
                self.assertEqual(lab.recover(), "sem_pendencia")

    def test_corrupt_candidate(self):
        with ReleaseLab() as lab:
            (lab.root / "v2/A.php").write_text("bad")
            with self.assertRaisesRegex(RuntimeError, "integridade"):
                lab.deploy(lambda _: True)
            self.assertEqual(lab.current(), "v1")
            self.assertFalse(lab.journal.exists())

    def test_corrupt_backup(self):
        with ReleaseLab() as lab:
            def smoke(_):
                (lab.root / "v1/A.php").write_text("bad")
                return False
            with self.assertRaisesRegex(RuntimeError, "integridade"):
                lab.deploy(smoke)
            self.assertTrue(lab.journal.exists())

    def test_lock(self):
        with ReleaseLab() as lab, lab.lock():
            with self.assertRaises(BlockingIOError):
                lab.deploy(lambda _: True)
            self.assertEqual(lab.current(), "v1")

    def test_env_changed(self):
        with ReleaseLab() as lab:
            lab.env.write_text("changed")
            with self.assertRaisesRegex(RuntimeError, "env_alterado"):
                lab.deploy(lambda _: True)
            self.assertEqual(lab.current(), "v1")


if __name__ == "__main__":
    unittest.main()
