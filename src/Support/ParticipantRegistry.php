<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CustomerAccounts\Support;

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use Liberu\Ecommerce\CustomerAccounts\Contracts\ParticipatesInPrivacyRequests;
use Liberu\Ecommerce\CustomerAccounts\Data\ParticipantRegistration;
use Liberu\Ecommerce\CustomerAccounts\Exceptions\ParticipantNotRegistered;

/**
 * Who takes part, read from configuration and never from discovery.
 *
 * There is no scan of installed packages and no `instanceof` probe of the
 * container. A module takes part because a human named it, and the registry is
 * readable, diffable and reviewable for that reason. The failure this prevents
 * is the one that cannot be seen: a module holding personal data that nobody
 * registered is not "missing from the case", it is *absent from the concept of
 * the case*, and the request completes and reports success over its rows.
 *
 * Resolution happens per commission rather than at boot, so a host that fixes a
 * broken binding does not have to reopen the case — the next retry sees it.
 */
final class ParticipantRegistry
{
    /** @return list<ParticipantRegistration> every registered participant, in configured order */
    public function all(): array
    {
        $registrations = [];

        /** @var array<string, mixed> $configured */
        $configured = Config::get('customer-accounts.participants', []);

        foreach ($configured as $name => $entry) {
            $registrations[] = ParticipantRegistration::fromConfig((string) $name, is_array($entry) ? $entry : []);
        }

        return $registrations;
    }

    public function get(string $name): ParticipantRegistration
    {
        foreach ($this->all() as $registration) {
            if ($registration->name === $name) {
                return $registration;
            }
        }

        throw ParticipantNotRegistered::named($name);
    }

    /**
     * The bound implementation, or null.
     *
     * Null is an answer this module knows how to hold: the participant is
     * registered and silent, the case goes partial, and the operator sees which
     * one. Falling back to a no-op implementation here would convert exactly
     * that visible gap into a reported success.
     */
    public function resolve(string $name): ?ParticipatesInPrivacyRequests
    {
        $registration = $this->get($name);

        $adapter = $registration->adapter;

        // A host may name a container binding or a concrete class. Neither
        // resolvable means registered-and-silent, which is the answer we want.
        if ($adapter === null || (! App::bound($adapter) && ! class_exists($adapter))) {
            return null;
        }

        $resolved = App::make($adapter);

        return $resolved instanceof ParticipatesInPrivacyRequests ? $resolved : null;
    }
}
