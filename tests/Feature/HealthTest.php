<?php

namespace Tests\Feature;

use Tests\TestCase;

class HealthTest extends TestCase
{
    public function test_exibe_apenas_estado_publico(): void
    {
        $response = $this->getJson('/saude');

        $response->assertOk();
        $response->assertExactJson(['status' => 'ok']);
        $this->assertSame('{"status":"ok"}', $response->getContent());
    }

    public function test_rota_inexistente_retorna_404(): void
    {
        $this->getJson('/rota-inexistente')->assertNotFound();
    }
}
