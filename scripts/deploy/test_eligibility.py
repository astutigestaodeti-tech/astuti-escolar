import importlib.util
from pathlib import Path
import unittest

spec = importlib.util.spec_from_file_location(
    "eligibility", Path(__file__).with_name("eligibility.py")
)
policy = importlib.util.module_from_spec(spec)
spec.loader.exec_module(policy)


class EligibilityTest(unittest.TestCase):
    def setUp(self):
        self.old = "a" * 40
        self.new = "b" * 40
        self.run_data = dict(head_sha=self.new, head_branch="main",
                             event="push", workflow_id=policy.WORKFLOW_ID,
                             status="completed", conclusion="success")
        self.jobs = [dict(name=name, status="completed", conclusion="success")
                     for name in sorted(policy.JOBS)]
        self.changes = [dict(path="app/Example.php", status="M",
                             old_mode="100644", new_mode="100644")]

    def decision(self):
        return policy.avaliar(self.old, self.new, self.run_data,
                              self.jobs, self.changes)

    def test_candidate(self):
        self.assertEqual(self.decision(),
                         (True, "candidato_sem_autorizacao_de_escrita"))

    def test_added_regular_php(self):
        self.changes[0].update(status="A", old_mode="000000")
        self.assertTrue(self.decision()[0])

    def test_invalid_ci(self):
        for key, value in (("head_sha", self.old), ("head_branch", "feature/x"),
                           ("event", "pull_request"), ("workflow_id", 0),
                           ("status", "in_progress"), ("conclusion", "failure")):
            with self.subTest(key=key):
                original = self.run_data[key]
                self.run_data[key] = value
                self.assertFalse(self.decision()[0])
                self.run_data[key] = original

    def test_missing_or_duplicate_job(self):
        original = self.jobs[:]
        self.jobs = original[:1]
        self.assertFalse(self.decision()[0])
        self.jobs = [original[0], original[0]]
        self.assertFalse(self.decision()[0])

    def test_failed_job(self):
        self.jobs[0]["conclusion"] = "skipped"
        self.assertFalse(self.decision()[0])

    def test_restricted_paths(self):
        for path in (".github/workflows/testes.yml", "composer.lock",
                     "database/migrations/a.php", "app/../secret.php",
                     "app/.hidden.php", "app/a.php\n", "resources/views/a.php"):
            with self.subTest(path=path):
                self.changes[0]["path"] = path
                self.assertFalse(self.decision()[0])

    def test_restricted_modes_and_status(self):
        for mode in ("120000", "160000", "100755", "000000"):
            with self.subTest(mode=mode):
                self.changes[0]["new_mode"] = mode
                self.assertFalse(self.decision()[0])
        self.changes[0]["new_mode"] = "100644"
        for status in ("D", "R", "T", "C"):
            with self.subTest(status=status):
                self.changes[0]["status"] = status
                self.assertFalse(self.decision()[0])

    def test_empty_or_duplicate_changes(self):
        self.changes *= 2
        self.assertFalse(self.decision()[0])
        self.changes = []
        self.assertFalse(self.decision()[0])

    def test_invalid_or_equal_sha(self):
        self.new = "invalid"
        self.assertFalse(self.decision()[0])
        self.new = self.old
        self.assertFalse(self.decision()[0])


if __name__ == "__main__":
    unittest.main()
