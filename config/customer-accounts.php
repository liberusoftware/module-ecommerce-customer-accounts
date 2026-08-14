<?php

declare(strict_types=1);

return [

    /*
     * The participant registry.
     *
     * Every module that holds personal data and takes part in a privacy request
     * is named here, by the host, at install time. **This is configuration and
     * not discovery**: a participant that is not named is invisible, and an
     * invisible participant is exactly how a module's rows survive an erasure
     * that reported success. There is no scan, no interface probe and no
     * convention — somebody decided, and the decision is readable.
     *
     * Each entry:
     *
     *   'commerce-customers' => [
     *       'label'   => 'Customer files',
     *       'adapter' => \App\Privacy\CommerceCustomersParticipant::class,
     *       'handles' => ['access', 'erasure'],
     *   ],
     *
     * `adapter` is a container key resolving to a ParticipatesInPrivacyRequests.
     * Null — or a key nothing is bound to — leaves the participant *registered
     * and unbound*, which answers "unavailable" and makes the case partial. That
     * is the point: a silent module is visible rather than absent.
     *
     * `handles` names the request kinds the module publishes. A participant that
     * does not publish an export is still a participant of an access request; it
     * answers unavailable and the export is delivered as explicitly partial. Six
     * modules in this fleet publish neither half — see docs/adoption.md.
     */
    'participants' => [],

    /*
     * The statutory response period, in days, and the zone it is counted in.
     *
     * A deadline is a date in a jurisdiction, never a bare timestamp: "within one
     * month" is a calendar answer, and a UTC instant read in Auckland is a
     * different day from the one the regulator means.
     */
    'deadline_days' => 30,
    'deadline_timezone' => 'UTC',

    /*
     * Guest order claims.
     *
     * `max_attempts` in `attempt_window_minutes` is the rate limit, counted per
     * merchant against both the presented order reference and the presented
     * address. An unlimited claim endpoint is an order-reference oracle, and the
     * limit belongs here rather than in one surface's middleware so that every
     * surface inherits it.
     */
    'claim' => [
        'token_ttl_minutes' => 60,
        'max_attempts' => 5,
        'attempt_window_minutes' => 60,
    ],

];
