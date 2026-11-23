<?php

namespace App\Waitlist;

use App\Models\Tenant\WaitlistRequest;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

class StageCalculator
{
    public function cutoff(): CarbonImmutable
    {
        $raw = (string) config('waitlist.cutoff_date');
        $date = CarbonImmutable::createFromFormat('!Y-m-d', $raw);

        if (! $date || $date->format('Y-m-d') !== $raw) {
            throw new InvalidArgumentException('Configure a data de corte em AAAA-MM-DD.');
        }

        return $date;
    }

    public function stageFor(string $birthDate): ?string
    {
        $birthday = CarbonImmutable::createFromFormat('!Y-m-d', $birthDate);
        $cutoff = $this->cutoff();

        if (! $birthday || $birthday->format('Y-m-d') !== $birthDate || $birthday->gt($cutoff)) {
            return null;
        }

        $age = (int) floor($birthday->diffInYears($cutoff));

        return match ($age) {
            0, 1 => 'bercario',
            2 => 'maternal_1',
            3 => 'maternal_2',
            4 => 'pre_1',
            5 => 'pre_2',
            default => null,
        };
    }

    public function labelFor(string $birthDate): ?string
    {
        $stage = $this->stageFor($birthDate);

        return $stage === null ? null : WaitlistRequest::STAGES[$stage];
    }
}
