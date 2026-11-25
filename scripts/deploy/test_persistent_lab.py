import subprocess
import sys
import tempfile
import unittest
from pathlib import Path

from persistent_lab import PersistentLab


class PersistentLabTest(unittest.TestCase):
    def crash(self, root, phase):
        script = """
import os
import sys
from persistent_lab import PersistentLab
lab = PersistentLab(sys.argv[1])
def checkpoint(phase):
    if phase == sys.argv[2]:
        os._exit(77)
lab.deploy(lambda _: True, checkpoint)
"""
        result = subprocess.run(
            [sys.executable, "-B", "-c", script, str(root), phase],
            cwd=Path(__file__).resolve().parent, timeout=20,
            capture_output=True,
        )
        self.assertEqual(result.returncode, 77, result.stderr.decode())

    def test_recovery_in_new_process(self):
        for phase in ("journal", "switched", "validated"):
            with self.subTest(phase=phase), tempfile.TemporaryDirectory() as root:
                self.crash(root, phase)
                script = """
import sys
from persistent_lab import PersistentLab
lab = PersistentLab(sys.argv[1])
assert lab.recover() == "revertido"
assert lab.current() == "v1"
assert lab.recover() == "sem_pendencia"
lab.verify("v1")
"""
                result = subprocess.run(
                    [sys.executable, "-B", "-c", script, root],
                    cwd=Path(__file__).resolve().parent, timeout=20,
                    capture_output=True,
                )
                self.assertEqual(result.returncode, 0, result.stderr.decode())
                self.assertFalse((Path(root) / "pending.json").exists())
                self.assertEqual(PersistentLab(root).current(), "v1")

    def test_corrupt_previous_version_blocks_recovery(self):
        with tempfile.TemporaryDirectory() as root:
            self.crash(root, "switched")
            (Path(root) / "v1/A.php").write_text("corrupt")
            with self.assertRaisesRegex(RuntimeError, "integridade"):
                PersistentLab(root)
            self.assertTrue((Path(root) / "pending.json").exists())

    def test_changed_env_blocks_recovery(self):
        with tempfile.TemporaryDirectory() as root:
            self.crash(root, "switched")
            (Path(root) / ".env").write_text("changed")
            with self.assertRaisesRegex(RuntimeError, "env_alterado"):
                PersistentLab(root)
            self.assertTrue((Path(root) / "pending.json").exists())

    def test_reopen_successful_deployment(self):
        with tempfile.TemporaryDirectory() as root:
            lab = PersistentLab(root)
            self.assertEqual(lab.deploy(lambda _: True), "aplicado")
            reopened = PersistentLab(root)
            self.assertEqual(reopened.current(), "v2")
            reopened.verify("v2")
            self.assertEqual(reopened.recover(), "sem_pendencia")

    def test_nonempty_uninitialized_directory_blocks(self):
        with tempfile.TemporaryDirectory() as root:
            path = Path(root) / "existing"
            path.write_text("preserve")
            with self.assertRaisesRegex(RuntimeError, "laboratorio_nao_vazio"):
                PersistentLab(root)
            self.assertEqual(path.read_text(), "preserve")


if __name__ == "__main__":
    unittest.main()
