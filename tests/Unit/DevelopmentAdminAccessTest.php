<?php

namespace Tests\Unit;

use App\Models\User;
use App\Security\DevelopmentAdminAccess;
use Illuminate\Http\Request;
use Tests\TestCase;

class DevelopmentAdminAccessTest extends TestCase
{
    public function test_excecao_falha_fechada_sem_ip_https_identidade_prazo_e_proxy_coerentes(): void
    {
        app()->detectEnvironment(fn () => 'local');
        config()->set('app.env', 'local');
        config()->set('auth.development_admin', [
            'enabled' => true,
            'ip' => '8.8.4.4',
            'expires_at' => (string) now()->addHour()->getTimestamp(),
        ]);
        $user = new User(['email' => 'admin@usuarios.astuti.invalid', 'status' => 'active']);
        $user->forceFill([
            'two_factor_secret' => 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ',
            'two_factor_confirmed_at' => now(),
        ]);
        $access = app(DevelopmentAdminAccess::class);
        $direct = Request::create('https://localhost/login', 'POST', server: ['REMOTE_ADDR' => '8.8.4.4']);
        $proxy = Request::create('https://localhost/login', 'POST', server: [
            'REMOTE_ADDR' => '127.0.0.1',
            'HTTP_X_FORWARDED_FOR' => '8.8.4.4',
            'HTTP_CF_CONNECTING_IP' => '8.8.4.4',
        ]);
        $oldProxies = Request::getTrustedProxies();
        $oldHeaders = Request::getTrustedHeaderSet();
        Request::setTrustedProxies(['127.0.0.1'], Request::HEADER_X_FORWARDED_FOR);
        try {
            $this->assertTrue($access->eligible($direct, $user));
            $this->assertTrue($access->eligible($proxy, $user));
            $proxy->headers->set('CF-Connecting-IP', '8.8.8.8');
            $this->assertFalse($access->eligible($proxy, $user));
            $proxy->headers->remove('CF-Connecting-IP');
            $this->assertFalse($access->eligible($proxy, $user));
            $spoof = Request::create('https://localhost/login', 'POST', server: [
                'REMOTE_ADDR' => '8.8.8.8',
                'HTTP_X_FORWARDED_FOR' => '8.8.4.4',
                'HTTP_CF_CONNECTING_IP' => '8.8.4.4',
            ]);
            $this->assertFalse($access->eligible($spoof, $user));
            $this->assertFalse($access->eligible(Request::create('http://localhost/login', 'POST', server: ['REMOTE_ADDR' => '8.8.4.4']), $user));
            $user->email = 'outro@usuarios.astuti.invalid';
            $this->assertFalse($access->eligible($direct, $user));
            $user->email = 'admin@usuarios.astuti.invalid';
            $user->status = 'inactive';
            $this->assertFalse($access->eligible($direct, $user));
            $user->status = 'active';
            $user->two_factor_confirmed_at = null;
            $this->assertFalse($access->eligible($direct, $user));
            $user->two_factor_confirmed_at = now();
            config()->set('auth.development_admin.ip', '127.0.0.1');
            $this->assertFalse($access->eligible(Request::create('https://localhost/login', 'POST', server: ['REMOTE_ADDR' => '127.0.0.1']), $user));
            config()->set('auth.development_admin.ip', '8.8.4.4');
            config()->set('auth.development_admin.expires_at', (string) now()->addDays(8)->getTimestamp());
            $this->assertFalse($access->eligible($direct, $user));
            config()->set('auth.development_admin.expires_at', '');
            $this->assertFalse($access->eligible($direct, $user));
            config()->set('auth.development_admin.expires_at', (string) now()->addHour()->getTimestamp());
            config()->set('app.env', 'production');
            $this->assertFalse($access->eligible($direct, $user));
        } finally {
            Request::setTrustedProxies($oldProxies, $oldHeaders);
            app()->detectEnvironment(fn () => 'testing');
            config()->set('app.env', 'testing');
        }
    }
}
