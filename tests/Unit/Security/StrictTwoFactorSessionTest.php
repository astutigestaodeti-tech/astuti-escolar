<?php

namespace Tests\Unit\Security;

use App\Models\User;
use App\Security\DevelopmentAdminAccess;
use App\Security\TwoFactorSession;
use App\Support\EmailNormalizer;
use Illuminate\Http\Request;
use Tests\TestCase;

final class StrictTwoFactorSessionTest extends TestCase
{
    public function test_development_exception_does_not_authorize_technical_console(): void
    {
        $this->app->instance('env', 'local');
        config([
            'app.env' => 'local',
            'auth.development_admin.enabled' => true,
            'auth.development_admin.ip' => '8.8.8.8',
            'auth.development_admin.expires_at' => (string) (now()->getTimestamp() + 3600),
        ]);

        $request = Request::create('https://example.test/agente', 'GET', [], [], [], [
            'REMOTE_ADDR' => '8.8.8.8',
            'HTTP_CF_CONNECTING_IP' => '8.8.8.8',
        ]);
        $request->setLaravelSession(app('session')->driver('array'));
        $user = $this->user();
        app(DevelopmentAdminAccess::class)->start($request, $user);

        $twoFactor = app(TwoFactorSession::class);
        $this->assertTrue($twoFactor->isVerified($request, $user));
        $this->assertFalse($twoFactor->isVerifiedWithoutBypass($request, $user));

        $twoFactor->markVerified($request, $user);
        $this->assertTrue($twoFactor->isVerifiedWithoutBypass($request, $user));
        $this->assertFalse($twoFactor->isVerifiedWithoutBypass($request, $this->user('inactive')));
        $this->assertFalse($twoFactor->isVerifiedWithoutBypass($request, $this->user('active', 'different-secret')));
        $this->assertFalse($twoFactor->isVerifiedWithoutBypass($request, $this->user('active', '')));
        $this->assertFalse($twoFactor->isVerifiedWithoutBypass($request, $this->user('active', 'test-secret', 'another-id')));

        $this->travel(13)->hours();
        $this->assertFalse($twoFactor->isVerifiedWithoutBypass($request, $user));
    }

    public function test_request_without_session_is_denied(): void
    {
        $this->assertFalse(app(TwoFactorSession::class)->isVerifiedWithoutBypass(
            Request::create('/agente'),
            $this->user(),
        ));
    }

    private function user(string $status = 'active', string $secret = 'test-secret', string $id = 'test-id'): User
    {
        $user = $this->createMock(User::class);
        $user->method('getKey')->willReturn($id);
        $user->method('getAttribute')->willReturnCallback(static fn ($key) => match ($key) {
            'status' => $status,
            'email' => EmailNormalizer::fromLogin('admin'),
            'two_factor_secret' => $secret,
            'two_factor_confirmed_at' => now(),
            default => null,
        });

        return $user;
    }
}
