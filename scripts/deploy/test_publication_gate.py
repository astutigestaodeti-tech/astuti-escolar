import tempfile
import unittest
from pathlib import Path
from unittest.mock import patch

from publication_gate import check, verify_metadata, verify_workflow

OLD = "a" * 40
NEW = "b" * 40


class PublicationGateTest(unittest.TestCase):
    @patch("publication_gate.verify_workflow")
    @patch("publication_gate.verify_metadata")
    def test_exact_plan(self, metadata, workflow):
        plan = {"eligible": True, "installed": OLD, "target": NEW,
                "write_authorized": False}
        self.assertEqual(check({"old": OLD, "new": NEW}, lambda *_: plan), plan)
        metadata.assert_called_once()
        workflow.assert_called_once()

    @patch("publication_gate.verify_workflow")
    @patch("publication_gate.verify_metadata")
    def test_rejects_divergent_or_ineligible_plan(self, metadata, workflow):
        plan = {"eligible": True, "installed": OLD, "target": NEW,
                "write_authorized": False}
        for changes in ({"eligible": False}, {"installed": NEW},
                        {"target": OLD}, {"write_authorized": True}):
            with self.subTest(changes=changes):
                with self.assertRaisesRegex(RuntimeError, "planejamento_nao_elegivel"):
                    check({"old": OLD, "new": NEW},
                          lambda *_: dict(plan, **changes))

    @patch("publication_gate.verify_metadata", side_effect=RuntimeError("inseguro"))
    def test_untrusted_metadata_blocks(self, metadata):
        with self.assertRaisesRegex(RuntimeError, "inseguro"):
            check({"old": OLD, "new": NEW}, lambda *_: self.fail("planner chamado"))

    @patch("publication_gate.trusted")
    @patch("publication_gate.git")
    def test_workflow_matches_both_commits(self, git, trusted):
        with tempfile.TemporaryDirectory() as tmp:
            workflow = Path(tmp) / "workflow.yml"
            workflow.write_bytes(b"name: Testes\n")
            git.return_value = workflow.read_bytes()
            verify_workflow(Path(tmp), {"old": OLD, "new": NEW}, workflow)
            self.assertEqual(git.call_count, 2)
            self.assertEqual(git.call_args.args[2], NEW + ":.github/workflows/testes.yml")

    @patch("publication_gate.trusted")
    @patch("publication_gate.git", side_effect=[b"trusted", b"changed"])
    def test_changed_workflow_blocks(self, git, trusted):
        with tempfile.TemporaryDirectory() as tmp:
            workflow = Path(tmp) / "workflow.yml"
            workflow.write_bytes(b"trusted")
            with self.assertRaisesRegex(RuntimeError, "workflow_divergente"):
                verify_workflow(Path(tmp), {"old": OLD, "new": NEW}, workflow)

    @patch("publication_gate.trusted")
    def test_external_objects_block(self, trusted):
        with tempfile.TemporaryDirectory() as tmp:
            repo = Path(tmp)
            info = repo / ".git/objects/info"
            info.mkdir(parents=True)
            (info / "alternates").write_text("/external")
            with self.assertRaisesRegex(RuntimeError, "metadados_externos"):
                verify_metadata(repo)

    @patch("publication_gate.trusted")
    def test_nested_file_checked(self, trusted):
        with tempfile.TemporaryDirectory() as tmp:
            repo = Path(tmp)
            (repo / ".git").mkdir()
            config = repo / ".git/config"
            config.write_text("fixture")
            verify_metadata(repo)
            trusted.assert_any_call(config, 0)

    @patch("publication_gate.verify_metadata")
    @patch("publication_gate.verify_workflow", side_effect=RuntimeError("workflow_divergente"))
    def test_workflow_refusal_precedes_planner(self, workflow, metadata):
        with self.assertRaisesRegex(RuntimeError, "workflow_divergente"):
            check({"old": OLD, "new": NEW}, lambda *_: self.fail("planner chamado"))


if __name__ == "__main__":
    unittest.main()
