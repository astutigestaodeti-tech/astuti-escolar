<?php

namespace Tests\Unit;

use App\Audit\AuditActor;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;

class AuditActorTest extends TestCase
{
    public function test_contexto_de_autoria_nao_vaza_entre_jobs_ou_em_excecoes(): void
    {
        $actor = app(AuditActor::class);
        $first = (string) Str::uuid();
        $second = (string) Str::uuid();

        $this->assertNull($actor->current());
        $this->assertSame('resultado', $actor->runAs($first, function () use ($actor, $first, $second): string {
            $this->assertSame($first, $actor->current());
            try {
                $actor->runAs($second, function () use ($actor, $second): never {
                    $this->assertSame($second, $actor->current());
                    throw new RuntimeException('Falha simulada');
                });
            } catch (RuntimeException $exception) {
                $this->assertSame('Falha simulada', $exception->getMessage());
            }

            $this->assertSame($first, $actor->current());

            return 'resultado';
        }));
        $this->assertNull($actor->current());
    }

    public function test_contexto_recusa_autoria_que_nao_seja_uuid(): void
    {
        $this->expectException(InvalidArgumentException::class);
        app(AuditActor::class)->runAs('sistema-anonimo', fn () => null);
    }
}
