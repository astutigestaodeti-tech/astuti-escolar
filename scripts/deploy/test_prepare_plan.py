"""Ensaio integrado com commits reais e CI simulado, sem deploy."""
import subprocess
import tempfile
import unittest
from pathlib import Path

from eligibility import JOBS, WORKFLOW_ID
from prepare_plan import prepare


class PreparePlanTest(unittest.TestCase):
    def setUp(self):
        temp = tempfile.TemporaryDirectory()
        self.addCleanup(temp.cleanup)
        self.repo = Path(temp.name)
        self.git("init", "-q", "-b", "main")
        self.git("config", "user.name", "Test")
        self.git("config", "user.email", "test@example.invalid")
        (self.repo / "app").mkdir()
        (self.repo / "app/A.php").write_text("<?php return 1;")
        self.old = self.commit()
        (self.repo / "app/A.php").write_text("<?php return 2;")
        (self.repo / "app/B.php").write_text("<?php return 3;")
        self.new = self.commit()
        self.git("checkout", "-q", "--detach", self.old)
        self.run = dict(head_sha=self.new, head_branch="main", event="push",
                        workflow_id=WORKFLOW_ID, status="completed",
                        conclusion="success")
        self.jobs = [dict(name=n, status="completed", conclusion="success")
                     for n in JOBS]

    def git(self, *args):
        return subprocess.check_output(
            ["git", "-C", str(self.repo), *args], stderr=subprocess.STDOUT
        ).decode().strip()

    def commit(self):
        self.git("add", "-A")
        self.git("commit", "-qm", "fixture")
        return self.git("rev-parse", "HEAD")

    def plan(self):
        return prepare(self.repo, self.old, self.new, self.run, self.jobs)

    def test_multiple_files_read_only(self):
        plan = self.plan()
        self.assertTrue(plan.eligible)
        self.assertEqual({c.path for c in plan.changes}, {"app/A.php", "app/B.php"})
        self.assertEqual(self.git("rev-parse", "HEAD"), self.old)
        self.assertEqual(self.git("status", "--porcelain"), "")
        self.assertFalse((self.repo / "app/B.php").exists())

    def test_ci_pending(self):
        self.run["status"] = "in_progress"
        plan = self.plan()
        self.assertFalse(plan.eligible)
        self.assertEqual(plan.changes, ())

    def test_wrong_sha(self):
        self.run["head_sha"] = self.old
        self.assertFalse(self.plan().eligible)

    def test_dirty(self):
        (self.repo / "app/A.php").write_text("local")
        self.assertFalse(self.plan().eligible)
        self.assertEqual((self.repo / "app/A.php").read_text(), "local")

    def test_restricted_change(self):
        self.git("checkout", "-q", "--detach", self.new)
        (self.repo / "composer.lock").write_text("{}")
        self.new = self.commit()
        self.run["head_sha"] = self.new
        self.git("checkout", "-q", "--detach", self.old)
        self.assertFalse(self.plan().eligible)

    def test_missing_job(self):
        self.jobs.pop()
        self.assertFalse(self.plan().eligible)


if __name__ == "__main__":
    unittest.main()
