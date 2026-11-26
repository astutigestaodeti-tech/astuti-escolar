import unittest
from unittest.mock import patch

from publication_gate import check

OLD = "a" * 40
NEW = "b" * 40


class PublicationGateTest(unittest.TestCase):
    @patch("publication_gate.trusted")
    def test_exact_plan(self, trusted):
        plan = {"eligible": True, "installed": OLD, "target": NEW,
                "write_authorized": False}
        self.assertEqual(check({"old": OLD, "new": NEW}, lambda *_: plan), plan)

    @patch("publication_gate.trusted")
    def test_rejects_divergent_or_ineligible_plan(self, trusted):
        plan = {"eligible": True, "installed": OLD, "target": NEW,
                "write_authorized": False}
        for changes in ({"eligible": False}, {"installed": NEW},
                        {"target": OLD}, {"write_authorized": True}):
            with self.subTest(changes=changes):
                with self.assertRaisesRegex(RuntimeError, "planejamento_nao_elegivel"):
                    check({"old": OLD, "new": NEW},
                          lambda *_: dict(plan, **changes))

    @patch("publication_gate.trusted", side_effect=RuntimeError("inseguro"))
    def test_untrusted_metadata_blocks(self, trusted):
        with self.assertRaisesRegex(RuntimeError, "inseguro"):
            check({"old": OLD, "new": NEW}, lambda *_: self.fail("planner chamado"))


if __name__ == "__main__":
    unittest.main()
