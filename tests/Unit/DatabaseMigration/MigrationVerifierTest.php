<?php

namespace Tests\Unit\DatabaseMigration;

use App\Support\DatabaseMigration\MigrationVerifier;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class MigrationVerifierTest extends TestCase
{
    public function test_somente_as_bases_ficticias_locais_podem_ser_alvos(): void
    {
        $platform = ['driver' => 'pgsql', 'host' => '127.0.0.1', 'port' => '5432', 'database' => 'astuti_platform_dev', 'url' => null];
        $tenant = ['driver' => 'pgsql', 'host' => 'localhost', 'port' => '5432', 'database' => 'astuti_monte_sereno_dev', 'url' => null];
        $source = ['driver' => 'mysql', 'host' => '127.0.0.1', 'database' => 'astuti_platform_dev', 'username' => 'somente_leitura'];

        $this->assertTrue(MigrationVerifier::safeContext('local', $platform, $tenant, $source));
        $this->assertFalse(MigrationVerifier::safeContext('production', $platform, $tenant, $source));
        $this->assertFalse(MigrationVerifier::safeContext('local', array_replace($platform, ['database' => 'prefeitura_real']), $tenant, $source));
        $this->assertFalse(MigrationVerifier::safeContext('local', $platform, array_replace($tenant, ['host' => 'db.publico.example']), $source));
        $this->assertFalse(MigrationVerifier::safeContext('local', $platform, $tenant, array_replace($source, ['database' => 'outra_prefeitura'])));
    }

    public function test_conferencia_bloqueia_perda_de_chaves_ou_mudanca_de_hash(): void
    {
        MigrationVerifier::assertSameIdentity(['b', 'a'], ['a', 'b'], 'students');
        $this->assertSame('hash-preservado', MigrationVerifier::preservePasswordHash('hash-preservado', 'hash-preservado'));

        $this->expectException(InvalidArgumentException::class);
        MigrationVerifier::assertSameIdentity(['a', 'b'], ['a'], 'students');
    }

    public function test_hash_de_senha_divergente_interrompe_copia(): void
    {
        $this->expectException(InvalidArgumentException::class);
        MigrationVerifier::preservePasswordHash('hash-original', 'hash-modificado');
    }
}
