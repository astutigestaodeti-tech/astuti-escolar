<?php

namespace Tests\Unit;

use Tests\TestCase;

class PortugueseValidationCompletenessTest extends TestCase
{
    public function test_i5_todas_as_chaves_de_validacao_do_framework_tem_traducao(): void
    {
        $reference = require base_path('vendor/laravel/framework/src/Illuminate/Translation/lang/en/validation.php');
        $translated = require base_path('lang/pt_BR/validation.php');
        $missing = [];

        $check = function (array $expected, array $actual, string $prefix = '') use (&$check, &$missing): void {
            foreach ($expected as $key => $value) {
                $path = $prefix.$key;
                if (! array_key_exists($key, $actual)) {
                    $missing[] = $path;
                } elseif (is_array($value)) {
                    if (! is_array($actual[$key])) {
                        $missing[] = $path;
                    } else {
                        $check($value, $actual[$key], $path.'.');
                    }
                }
            }
        };

        $check($reference, $translated);

        $this->assertSame([], $missing, 'Traduções obrigatórias ausentes: '.implode(', ', $missing));
    }
}
