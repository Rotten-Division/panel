<?php

namespace App\Services\Auth;

use App\Contracts\Auth\SelfServiceRegistrationPolicy;

class AlwaysAllowRegistrationPolicy implements SelfServiceRegistrationPolicy
{
    public function denialReason(): ?string
    {
        return null;
    }
}
