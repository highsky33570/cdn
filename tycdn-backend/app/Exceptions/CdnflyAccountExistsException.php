<?php

namespace App\Exceptions;

/**
 * CDNfly already holds an account for this email, so no new one can be created.
 *
 * Adopting it is not automatic. The upstream account may be one an earlier
 * partial run of this portal created and lost track of — the ordinary case, and
 * safe to take over — or it may be an account the panel operator made by hand,
 * including a privileged one. Handing its API credentials to a portal user would
 * let that user act as whoever owns it.
 *
 * So the decision is surfaced rather than taken: this carries what was found so
 * an operator can look at it and choose.
 */
class CdnflyAccountExistsException extends \RuntimeException
{
    public function __construct(
        public readonly int $cdnflyUserId,
        public readonly string $username,
        public readonly string $email,
    ) {
        parent::__construct(
            "CDNfly already has user #{$cdnflyUserId} ({$username}) for {$email}."
        );
    }
}
