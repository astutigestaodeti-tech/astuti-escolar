import fcntl
import unittest

from lab_transaction import laboratory, LabError, digest


class LabTransactionTest(unittest.TestCase):
    def test_apply(self):
        with laboratory() as lab:
            candidate = b"<?php return 2;\n"
            self.assertEqual(lab.exercise(candidate, lambda data: data == candidate),
                             "aplicado")
            self.assertEqual((lab.base / "app/Example.php").read_bytes(), candidate)
            self.assertEqual((lab.base / ".env").read_bytes(), b"APP_ENV=testing\n")

    def test_smoke_failure_rolls_back(self):
        with laboratory() as lab:
            original = (lab.base / "app/Example.php").read_bytes()
            self.assertEqual(lab.exercise(b"bad", lambda data: False), "revertido")
            self.assertEqual((lab.base / "app/Example.php").read_bytes(), original)
            self.assertEqual((lab.base / "backup/SHA256").read_text(), digest(original))

    def test_smoke_exception_rolls_back(self):
        def fail(data):
            raise RuntimeError("fixture")
        with laboratory() as lab:
            original = (lab.base / "app/Example.php").read_bytes()
            self.assertEqual(lab.exercise(b"bad", fail), "revertido")
            self.assertEqual((lab.base / "app/Example.php").read_bytes(), original)

    def test_lock_blocks(self):
        with laboratory() as lab:
            with (lab.base / "lock").open("ab") as lock:
                fcntl.flock(lock, fcntl.LOCK_EX | fcntl.LOCK_NB)
                with self.assertRaisesRegex(LabError, "concorrencia"):
                    lab.exercise(b"bad", lambda data: True)
            self.assertFalse((lab.base / "backup").exists())

    def test_existing_backup_blocks(self):
        with laboratory() as lab:
            (lab.base / "backup").mkdir()
            original = (lab.base / "app/Example.php").read_bytes()
            with self.assertRaises(FileExistsError):
                lab.exercise(b"bad", lambda data: True)
            self.assertEqual((lab.base / "app/Example.php").read_bytes(), original)

    def test_invalid_fixture(self):
        with laboratory() as lab:
            with self.assertRaises(LabError):
                lab.exercise(b"x" * 65537, lambda data: True)

    def test_lab_removed(self):
        with laboratory() as lab:
            path = lab.base
            self.assertTrue(path.exists())
        self.assertFalse(path.exists())


if __name__ == "__main__":
    unittest.main()
