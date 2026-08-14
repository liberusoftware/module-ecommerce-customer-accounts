<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CustomerAccounts\Data;

use Carbon\CarbonImmutable;
use JsonSerializable;

/**
 * The assembled answer to an access request — assembled, never streamed from a
 * controller.
 *
 * `complete` is false whenever any registered participant did not contribute,
 * and `missing` names them. A partial export is deliverable; a partial export
 * presented as whole is the same lie as "your data has been erased" from a
 * function that wrote ten tables and knew about six modules.
 */
final readonly class ExportArtefact implements JsonSerializable
{
    /**
     * @param  array<string, array<string, mixed>>  $contributions  participant name => its payload
     * @param  list<string>  $missing
     */
    public function __construct(
        public string $requestReference,
        public string $subjectRef,
        public CarbonImmutable $assembledAt,
        public bool $complete,
        public array $contributions,
        public array $missing,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'request_reference' => $this->requestReference,
            'subject_ref' => $this->subjectRef,
            'assembled_at' => $this->assembledAt->toIso8601String(),
            'complete' => $this->complete,
            'missing_participants' => $this->missing,
            'contributions' => $this->contributions,
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
