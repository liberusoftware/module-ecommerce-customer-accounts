<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CustomerAccounts\Data;

use Liberu\Ecommerce\CustomerAccounts\Enums\RequestKind;

/** One line of the registry: who takes part, what they publish, and what answers for them. */
final readonly class ParticipantRegistration
{
    /** @param list<RequestKind> $handles */
    public function __construct(
        public string $name,
        public string $label,
        public ?string $adapter = null,
        public array $handles = [],
    ) {}

    /** @param array<string, mixed> $entry */
    public static function fromConfig(string $name, array $entry): self
    {
        $handles = [];

        /** @var list<string> $declared */
        $declared = is_array($entry['handles'] ?? null) ? array_values($entry['handles']) : [];

        foreach ($declared as $kind) {
            $parsed = RequestKind::tryFrom((string) $kind);
            if ($parsed !== null) {
                $handles[] = $parsed;
            }
        }

        return new self(
            name: $name,
            label: isset($entry['label']) ? (string) $entry['label'] : $name,
            adapter: isset($entry['adapter']) ? (string) $entry['adapter'] : null,
            handles: $handles,
        );
    }

    /**
     * A participant that declares nothing publishes nothing.
     *
     * The default is deliberately the pessimistic one: an entry written as
     * `'promotions' => []` is a module somebody registered and did not say
     * anything about, and reading that as "handles everything" would produce
     * cases that wait forever on a module that has no erasure to run.
     */
    public function handles(RequestKind $kind): bool
    {
        return in_array($kind, $this->handles, true);
    }
}
