#!/usr/bin/env bash
# Ensaio de CI somente em runners descartáveis; não usar em VPS operacional.
set -euo pipefail
[[ "${GITHUB_ACTIONS:-}" == true && "${TENANCY_INTEGRATION:-}" == 1 ]] || {
    echo "Exclusivo para o CI descartável." >&2
    exit 1
}
for db in "$PLATFORM_DB_DATABASE" "$TENANCY_TEST_DB_A" "$TENANCY_TEST_DB_B"; do
    [[ "$db" =~ ^[a-z0-9_]+_test$ ]] || exit 1
done
[[ "$PLATFORM_DB_HOST" == 127.0.0.1 && "$TENANCY_TEST_DB_HOST" == 127.0.0.1 ]] || exit 1
[[ "$PLATFORM_DB_DATABASE" != "$TENANCY_TEST_DB_A" && "$PLATFORM_DB_DATABASE" != "$TENANCY_TEST_DB_B" && "$TENANCY_TEST_DB_A" != "$TENANCY_TEST_DB_B" ]] || exit 1

work="$(mktemp -d)"
trap 'rm -rf -- "$work"' EXIT
pattern='/(OcrDocumentImportTest|OcrStudentImportScreenTest)::/'
export LC_ALL=C.UTF-8

# Listar com o mesmo PHPUnit instalado; o filtro complementar garante que
# novos testes entrem no grupo geral sem manutenção de uma lista manual.
inventory() {
    local name="$1"
    shift
    vendor/bin/phpunit --colors=never --list-tests "$@" > "$work/$name.raw"
    sed -n 's/^ *- //p' "$work/$name.raw" | LC_ALL=C sort > "$work/$name"
    test -s "$work/$name"
    test -z "$(uniq -d "$work/$name")"
}
inventory todos
inventory geral --exclude-filter "$pattern"
inventory ocr --filter "$pattern"
cat "$work/geral" "$work/ocr" | LC_ALL=C sort > "$work/uniao"
diff -u "$work/todos" "$work/uniao"
test -z "$(LC_ALL=C comm -12 "$work/geral" "$work/ocr")"
case "$ASTUTI_CI_GRUPO" in
    geral) args=(--exclude-filter "$pattern") ;;
    ocr) args=(--filter "$pattern") ;;
    *) exit 1 ;;
esac
expected="$(wc -l < "$work/$ASTUTI_CI_GRUPO")"
printf 'Inventário: total=%s geral=%s OCR=%s\n' "$(wc -l < "$work/todos")" "$(wc -l < "$work/geral")" "$(wc -l < "$work/ocr")"

# Duas rodadas no mesmo runner e nos mesmos bancos do grupo, sem
# apagar bancos, limpar o estado entre rodadas ou modificar fixtures.
for round in 1 2; do
    start=$SECONDS
    echo "::group::Grupo $ASTUTI_CI_GRUPO rodada $round"
    vendor/bin/phpunit --colors=never --fail-on-empty-test-suite --fail-on-incomplete --log-junit "$work/round-$round.xml" "${args[@]}"
    echo "::endgroup::"
    elapsed=$((SECONDS - start))
    python3 - "$work/round-$round.xml" "$expected" "$round" "$elapsed" <<'PY'
import os
import sys
import xml.etree.ElementTree as ET

path, expected, round_number, elapsed = sys.argv[1:]
cases = list(ET.parse(path).iter("testcase"))
if len(cases) != int(expected):
    raise SystemExit(f"Quantidade executada diverge do inventário: {len(cases)} != {expected}")
skipped = sum(case.find("skipped") is not None for case in cases)
if any(case.find("failure") is not None or case.find("error") is not None for case in cases):
    raise SystemExit("Há falhas no relatório JUnit.")
assertions = sum(int(case.attrib.get("assertions", 0)) for case in cases)
line = (
    f"Grupo {os.environ['ASTUTI_CI_GRUPO']}, rodada {round_number}: "
    f"{len(cases)} testes, {skipped} ignorados, {assertions} asserções, {elapsed}s"
)
print(line)
with open(os.environ["GITHUB_STEP_SUMMARY"], "a", encoding="utf-8") as summary:
    summary.write(line + "\n\n")
PY
done
