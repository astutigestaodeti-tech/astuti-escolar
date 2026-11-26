<?php

namespace App\Developer;

use App\Models\User;
use Illuminate\Support\Str;

final class AgentAccess
{
    public function allows(?User $user): bool
    {
        $uuid = config('agent.developer_uuid');

        return app()->environment('local')
            && config('app.env') === 'local'
            && is_string($uuid)
            && Str::isUuid($uuid)
            && $user !== null
            && $user->status === 'active'
            && hash_equals($uuid, (string) $user->getKey());
    }
}
