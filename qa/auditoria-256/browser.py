"""Primeiro cenário real de navegador, somente em runner e bases fictícias."""
import base64
import hashlib
import hmac
import os
import struct
import time
from playwright.sync_api import sync_playwright, expect

assert os.environ.get("GITHUB_ACTIONS") == "true"
assert os.environ["PLATFORM_DB_DATABASE"] == "astuti_platform_test"
base = "http://127.0.0.1:8765"
school = "/m/municipio-a/escolas/11111111-1111-4111-8111-111111111111"
other = "/m/municipio-a/escolas/22222222-2222-4222-8222-222222222222"
name = "Alice Fictícia Auditoria"
guardian = "Responsável Fictício Auditoria"

def totp():
    key = base64.b32decode("GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ")
    digest = hmac.new(key, struct.pack(">Q", int(time.time()) // 30), hashlib.sha1).digest()
    off = digest[-1] & 15
    return f"{(struct.unpack('>I', digest[off:off+4])[0] & 0x7fffffff) % 1000000:06d}"

with sync_playwright() as p:
    browser = p.chromium.launch()
    page = browser.new_page(viewport={"width": 1440, "height": 900})
    # Não permitir rede externa ou consulta a provedores com dados da fixture.
    page.route("**/*", lambda route: route.continue_() if route.request.url.startswith(base + "/") else route.abort())
    page.goto(base + "/login")
    page.get_by_label("E-mail ou usuário").fill("secretaria-qa@example.invalid")
    page.get_by_label("Senha", exact=True).fill("SenhaForteDeTeste123!")
    page.get_by_role("button", name="Acessar", exact=True).click()
    expect(page.locator("#code")).to_be_visible()
    page.locator("#code").fill(totp())
    page.get_by_role("button", name="Confirmar acesso").click()
    expect(page.locator("#code")).to_have_count(0)
    response = page.goto(base + school + "/alunos/novo")
    assert response.status == 200
    expect(page.locator("#full_name")).to_be_visible()
    page.locator("#full_name").fill(name)
    page.locator("#birth_date").fill("2999-01-01")
    page.locator("#school_year_id").select_option(label="2027")
    page.get_by_role("button", name="Cadastrar e matricular").click()
    expect(page.locator("#birth_date-erro")).to_contain_text("futuro")
    expect(page.locator("#birth_date")).to_have_attribute("aria-invalid", "true")
    page.locator("#birth_date").fill("2019-05-10")
    page.get_by_role("button", name="Cadastrar e matricular").click()
    expect(page.get_by_text("Aluno cadastrado e matriculado nesta escola.")).to_be_visible()
    assert page.url.startswith(base + school + "/alunos/")
    student_url = page.url
    page.goto(student_url + "/responsaveis/novo")
    page.locator("#full_name").fill(guardian)
    page.locator("#phone").fill("11987654321")
    page.locator("#relationship").select_option("mother")
    page.locator('[name="is_primary_contact"]').check()
    page.get_by_role("button", name="Vincular responsável", exact=True).click()
    page.goto(student_url)
    expect(page.get_by_text(guardian, exact=True).first).to_be_visible()
    page.goto(student_url + "/imprimir")
    expect(page.get_by_text("Selecione as informações")).to_be_visible()
    response = page.goto(base + other + "/alunos/novo")
    assert response.status == 403, response.status
    page.goto(student_url)
    expect(page.get_by_text(guardian, exact=True).first).to_be_visible()
    browser.close()
print("E2E: login + TOTP, nascimento futuro recusado, criação, vínculo, consulta de ficha e isolamento de escola aprovados.")
