<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CustomerAccounts\Enums;

/**
 * The Art. 6 basis the request is being answered under. It is recorded because a
 * case is a legal artefact before it is a technical one, and "why were we
 * allowed to do this" is the question an audit asks first.
 */
enum LawfulBasis: string
{
    case Consent = 'consent';
    case Contract = 'contract';
    case LegalObligation = 'legal_obligation';
    case VitalInterests = 'vital_interests';
    case PublicTask = 'public_task';
    case LegitimateInterests = 'legitimate_interests';

    public function label(): string
    {
        return match ($this) {
            self::Consent => 'Consent',
            self::Contract => 'Performance of a contract',
            self::LegalObligation => 'Legal obligation',
            self::VitalInterests => 'Vital interests',
            self::PublicTask => 'Public task',
            self::LegitimateInterests => 'Legitimate interests',
        };
    }
}
