import subprocess
import tempfile
import unittest
from pathlib import Path

from git_metadata import collect, MetadataError
from eligibility import avaliar, JOBS, WORKFLOW_ID


class GitMetadataTest(unittest.TestCase):
    def setUp(self):
        self.tmp = tempfile.TemporaryDirectory()
        self.addCleanup(self.tmp.cleanup)
        self.repo = Path(self.tmp.name)
        self.git("init", "-q", "-b", "main")
        self.git("config", "user.name", "Test")
        self.git("config", "user.email", "test@example.invalid")
        (self.repo / "app").mkdir()
        self.file = self.repo / "app/A.php"
        self.file.write_text("<?php return 1;")
        self.old = self.commit()
        self.file.write_text("<?php return 2;")
        self.new = self.commit()
        self.git("checkout", "-q", "--detach", self.old)

    def git(self, *args):
        return subprocess.check_output(["git", "-C", str(self.repo), *args],
                                       stderr=subprocess.STDOUT).decode().strip()

    def commit(self):
        self.git("add", "-A")
        self.git("commit", "-qm", "fixture")
        return self.git("rev-parse", "HEAD")

    def candidate(self, target):
        changes = collect(self.repo, self.old, target)
        run = dict(head_sha=target, head_branch="main", event="push",
                   workflow_id=WORKFLOW_ID, status="completed", conclusion="success")
        jobs = [dict(name=name, status="completed", conclusion="success")
                for name in JOBS]
        return avaliar(self.old, target, run, jobs, changes)[0]

    def test_regular_change(self):
        self.assertEqual(collect(self.repo, self.old, self.new), [
            dict(path="app/A.php", status="M", old_mode="100644", new_mode="100644")
        ])
        self.assertTrue(self.candidate(self.new))
        self.assertEqual(self.git("rev-parse", "HEAD"), self.old)

    def test_dirty_tracked(self):
        self.file.write_text("local")
        with self.assertRaises(MetadataError):
            collect(self.repo, self.old, self.new)

    def test_dirty_untracked(self):
        (self.repo / "extra").write_text("local")
        with self.assertRaises(MetadataError):
            collect(self.repo, self.old, self.new)

    def test_wrong_head(self):
        with self.assertRaises(MetadataError):
            collect(self.repo, self.new, self.old)

    def test_divergent(self):
        self.file.write_text("<?php return 3;")
        other = self.commit()
        with self.assertRaises(MetadataError):
            collect(self.repo, other, self.new)

    def test_missing_object(self):
        with self.assertRaises(MetadataError):
            collect(self.repo, self.old, "f" * 40)

    def test_symlink_blocked(self):
        (self.repo / "app/Link.php").symlink_to("A.php")
        target = self.commit()
        self.git("checkout", "-q", "--detach", self.old)
        self.assertFalse(self.candidate(target))

    def test_restricted_file_blocked(self):
        (self.repo / "composer.lock").write_text("{}")
        target = self.commit()
        self.git("checkout", "-q", "--detach", self.old)
        self.assertFalse(self.candidate(target))

    def test_newline_path_blocked(self):
        (self.repo / "app/Bad\n.php").write_text("<?php")
        target = self.commit()
        self.git("checkout", "-q", "--detach", self.old)
        self.assertFalse(self.candidate(target))


if __name__ == "__main__":
    unittest.main()
