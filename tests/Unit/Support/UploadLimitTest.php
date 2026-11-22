<?php

namespace Tests\Unit\Support;

use App\Support\UploadLimit;
use PHPUnit\Framework\TestCase;

class UploadLimitTest extends TestCase
{
    public function test_converte_valores_do_php_e_escreve_em_portugues(): void
    {
        $this->assertSame(2097152, UploadLimit::iniBytes('2M'));
        $this->assertSame(512 * 1024, UploadLimit::iniBytes('512K'));
        $this->assertSame(1073741824, UploadLimit::iniBytes('1G'));
        $this->assertSame(1500, UploadLimit::iniBytes('1500'));

        $this->assertSame('2 MB', UploadLimit::label(2097152));
        $this->assertSame('1,5 MB', UploadLimit::label(1572864));
        $this->assertSame('512 KB', UploadLimit::label(524288));
    }
}
