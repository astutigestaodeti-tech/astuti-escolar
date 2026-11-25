import copy
import unittest
from unittest.mock import patch

from operational_plan import operational_plan, snapshot

TARGET = "a" * 40
INSTALLED = "b" * 40


class OperationalPlanTest(unittest.TestCase):
    def setUp(self):
        self.branch = {"commit": {"sha": TARGET}, "protected": True}
        self.run = {"id": 123, "head_sha": TARGET, "head_branch": "main",
                    "event": "push", "workflow_id": 425304833,
                    "status": "completed", "conclusion": "success",
                    "run_attempt": 2}
        self.runs = {"total_count": 1, "workflow_runs": [self.run]}
        self.jobs = {"total_count": 2, "jobs": [
            {"name": name, "run_id": 123, "run_attempt": 2,
             "head_sha": TARGET, "status": "completed", "conclusion": "success"}
            for name in ("Testes PHP e interface",
                         "PostgreSQL 17 como usuario web restrito")]}

    def read(self, path):
        if path == "/branches/main":
            return copy.deepcopy(self.branch)
        if path.startswith("/actions/workflows/425304833/runs?"):
            self.assertIn("head_sha=" + TARGET, path)
            return copy.deepcopy(self.runs)
        if path == "/actions/runs/123/attempts/2/jobs?per_page=100":
            return copy.deepcopy(self.jobs)
        self.fail("rota inesperada: " + path)

    def test_snapshot_exact_attempt(self):
        self.assertEqual(snapshot(self.read), (TARGET, self.run, self.jobs["jobs"]))

    def test_unprotected_main(self):
        self.branch["protected"] = False
        with self.assertRaisesRegex(ValueError, "main_nao_protegida"):
            snapshot(self.read)

    def test_incomplete_runs(self):
        self.runs["total_count"] = 2
        with self.assertRaisesRegex(ValueError, "execucoes_incompletas"):
            snapshot(self.read)

    def test_latest_failure(self):
        self.runs["workflow_runs"].append(dict(self.run, id=124, conclusion="failure"))
        self.runs["total_count"] = 2
        with self.assertRaisesRegex(ValueError, "ci_nao_aprovado"):
            snapshot(self.read)

    def test_incomplete_jobs(self):
        self.jobs["total_count"] = 3
        with self.assertRaisesRegex(ValueError, "jobs_incompletos"):
            snapshot(self.read)

    def test_wrong_attempt(self):
        self.jobs["jobs"][0]["run_attempt"] = 1
        with self.assertRaisesRegex(ValueError, "job_divergente"):
            snapshot(self.read)

    def test_no_update(self):
        result = operational_plan(None, TARGET, self.read)
        self.assertFalse(result["eligible"])
        self.assertEqual(result["reason"], "sem_atualizacao")

    @patch("operational_plan.git", return_value=b"wrong")
    def test_stale_local_ref(self, git):
        with self.assertRaisesRegex(ValueError, "referencia_local_desatualizada"):
            operational_plan(None, INSTALLED, self.read)

    @patch("operational_plan.git", return_value=TARGET.encode())
    @patch("operational_plan.prepare")
    def test_plan_never_authorizes_write(self, prepare, git):
        from prepare_plan import Plan
        prepare.return_value = Plan(INSTALLED, TARGET, True, "candidate")
        result = operational_plan(None, INSTALLED, self.read)
        self.assertTrue(result["eligible"])
        self.assertFalse(result["write_authorized"])

    @patch("operational_plan.git", return_value=TARGET.encode())
    @patch("operational_plan.prepare")
    def test_changed_snapshot_blocks(self, prepare, git):
        def changed(*args):
            self.run["conclusion"] = "failure"
        prepare.side_effect = changed
        with self.assertRaisesRegex(ValueError, "ci_nao_aprovado"):
            operational_plan(None, INSTALLED, self.read)


if __name__ == "__main__":
    unittest.main()
