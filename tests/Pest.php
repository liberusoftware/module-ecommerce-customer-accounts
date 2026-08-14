<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use Liberu\Ecommerce\CustomerAccounts\Actions\OpenPrivacyRequest;
use Liberu\Ecommerce\CustomerAccounts\Data\PrivacyRequestDraft;
use Liberu\Ecommerce\CustomerAccounts\Enums\LawfulBasis;
use Liberu\Ecommerce\CustomerAccounts\Enums\RequestKind;
use Liberu\Ecommerce\CustomerAccounts\Enums\RequestScope;
use Liberu\Ecommerce\CustomerAccounts\Models\PrivacyRequest;
use Liberu\PackageTestbench\PackageTestCase;

uses(PackageTestCase::class, RefreshDatabase::class)->in('Feature');

/**
 * Register participants and bind their adapters in one step.
 *
 * @param  array<string, array{handles?: list<string>, adapter?: object|string|null, label?: string}>  $entries
 */
function participants(array $entries): void
{
    $config = [];

    foreach ($entries as $name => $entry) {
        $adapter = $entry['adapter'] ?? null;

        if (is_object($adapter)) {
            $key = $adapter::class.'@'.$name;
            App::instance($key, $adapter);
            $adapter = $key;
        }

        $config[$name] = [
            'label' => $entry['label'] ?? ucfirst($name),
            'adapter' => $adapter,
            'handles' => $entry['handles'] ?? ['access', 'erasure'],
        ];
    }

    Config::set('customer-accounts.participants', $config);
}

function draft(
    RequestKind $kind = RequestKind::Erasure,
    RequestScope $scope = RequestScope::Tenant,
    string $tenantId = 'tenant-a',
    string $subjectRef = 'person-1',
    ?string $reauthenticatedVia = 'password',
    ?CarbonImmutable $requestedAt = null,
): PrivacyRequestDraft {
    return new PrivacyRequestDraft(
        tenantId: $tenantId,
        subjectRef: $subjectRef,
        kind: $kind,
        scope: $scope,
        lawfulBasis: LawfulBasis::LegalObligation,
        requestedAt: $requestedAt ?? CarbonImmutable::parse('2026-08-01T09:00:00Z'),
        requestedByRef: 'actor-1',
        reason: 'the subject asked',
        reauthenticatedVia: $reauthenticatedVia,
    );
}

function openCase(
    RequestKind $kind = RequestKind::Erasure,
    RequestScope $scope = RequestScope::Tenant,
    string $tenantId = 'tenant-a',
    string $subjectRef = 'person-1',
): PrivacyRequest {
    return (new OpenPrivacyRequest())(draft($kind, $scope, $tenantId, $subjectRef));
}

/** @return list<string> every PHP file under src/, absolute. */
function sourceFiles(): array
{
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__).'/src'));
    $paths = [];

    foreach ($files as $file) {
        if ($file instanceof SplFileInfo && $file->isFile() && $file->getExtension() === 'php') {
            $paths[] = $file->getPathname();
        }
    }

    sort($paths);

    return $paths;
}

/**
 * A source file with its comments stripped.
 *
 * Every boundary rule below is about what the code does, and these files explain
 * the host faults they exist to prevent — so a naive grep for `now(` or `float`
 * finds the prose describing the defect rather than the defect.
 */
function sourceCode(string $path): string
{
    $code = '';

    foreach (token_get_all((string) file_get_contents($path)) as $token) {
        if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
            continue;
        }

        $code .= is_array($token) ? $token[1] : $token;
    }

    return $code;
}
