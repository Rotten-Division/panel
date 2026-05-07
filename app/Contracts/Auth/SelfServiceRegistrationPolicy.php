<?php

namespace App\Contracts\Auth;

/**
 * gate self service account creation paths, e.g. the registration page and
 * the OAuth first time sign in flow. admin initiated user creation is out of
 * scope, those paths are guarded by the usual permission checks.
 *
 * the panel binds an always allow default and the onboarding plugin rebinds
 * to a settings backed implementation. callers receive null when the path
 * should proceed and a user facing message string when it should not.
 */
interface SelfServiceRegistrationPolicy
{
    public function denialReason(): ?string;
}
