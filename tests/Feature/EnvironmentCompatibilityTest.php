<?php

namespace Tests\Feature;

use Tests\TestCase;

class EnvironmentCompatibilityTest extends TestCase
{
    public function test_requisito_de_php_reflete_a_versao_minima_do_lock(): void
    {
        $composer = json_decode(file_get_contents(base_path('composer.json')), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame('^8.4.1', $composer['require']['php']);
    }
}
