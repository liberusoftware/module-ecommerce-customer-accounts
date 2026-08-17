<?php

declare(strict_types=1);

it('never references the host application', function (): void {
    foreach (sourceFiles() as $file) {
        expect(sourceCode($file))->not->toMatch('/(?:use|new|extends|implements)\s+App\\\\/');
    }
});

it('depends on no sibling module', function (): void {
    // Stricter than previous waves and deliberately so: this module coordinates
    // nineteen of them, six publish no erasure at all, and a coordinator that
    // hard-depended on the four that do would be uninstallable for the reason
    // that it coordinates.
    foreach (sourceFiles() as $file) {
        expect(sourceCode($file))->not->toMatch('/Liberu\\\\Ecommerce\\\\(?!CustomerAccounts)/');
    }

    $composer = json_decode((string) file_get_contents(dirname(__DIR__, 2).'/composer.json'), true);

    foreach (array_keys(array_merge($composer['require'], $composer['require-dev'])) as $package) {
        expect($package)->not->toStartWith('liberusoftware/ecommerce-');
    }
});

it('reaches for no framework-foundation helper', function (): void {
    // config(), app(), auth() and now() live in laravel/framework, not in
    // illuminate/support. They pass CI because the testbench drags the framework
    // in, and they are a lying constraint for a real consumer.
    foreach (sourceFiles() as $file) {
        expect(sourceCode($file))
            ->not->toMatch('/(?<![\w>$:])config\(/')
            ->and(sourceCode($file))->not->toMatch('/(?<![\w>$:])app\(/')
            ->and(sourceCode($file))->not->toMatch('/(?<![\w>$:])auth\(/')
            ->and(sourceCode($file))->not->toMatch('/(?<![\w>$:])now\(/');
    }
});

it('mints no identifier that needs a package it does not require', function (): void {
    // Str::ulid() needs symfony/uid, which illuminate/support does not require.
    foreach (sourceFiles() as $file) {
        expect(sourceCode($file))->not->toContain('Str::ulid');
        expect(sourceCode($file))->not->toContain('Str::uuid');
    }
});

it('joins nothing and touches no table it does not own', function (): void {
    // Identifiers are opaque strings and there are no foreign keys out of this
    // module. A join here would be this module reading somebody else's rows to
    // satisfy a claim, which is the one thing a module that owns claims may not
    // do: we hold the claim and we ask the owner.
    foreach (sourceFiles() as $file) {
        expect(sourceCode($file))
            ->not->toContain('DB::table')
            ->and(sourceCode($file))->not->toContain('->join(')
            ->and(sourceCode($file))->not->toContain('->leftJoin(');
    }
});

it('computes no money and holds no float', function (): void {
    foreach (sourceFiles() as $file) {
        expect(sourceCode($file))
            ->not->toContain('(float)')
            ->and(sourceCode($file))->not->toContain('floatval')
            ->and(sourceCode($file))->not->toMatch('/\bfloat\s+\$/');
    }
});

it('writes the attempt record through the model, never through the builder', function (): void {
    // The append-only guard is a model hook, and a model hook does not fire for
    // query()->update() or ->delete(). Stating it here is what makes the
    // guarantee true rather than merely intended.
    foreach (sourceFiles() as $file) {
        $code = sourceCode($file);

        if (! str_contains($code, 'ClaimAttempt::query()')) {
            continue;
        }

        expect($code)->not->toMatch('/ClaimAttempt::query\(\)[^;]*->(update|delete)\(/s');
    }
});

it('restates a tenant on a relation only through the guard', function (): void {
    // The obvious form — ->where('tenant_id', (string) $this->tenant_id) — is
    // correct through a loaded parent and zeroes every withCount() and
    // whereHas(), because those build the relation from an instance whose
    // tenant_id is null. It ships green from inside a domain package and breaks
    // in the first surface that counts, which is why no test here caught it.
    // RestatesTenant is the guarded form; this rule is what stops the next
    // relation being written the obvious way.
    foreach (sourceFiles() as $file) {
        if (! str_contains($file, DIRECTORY_SEPARATOR.'Models'.DIRECTORY_SEPARATOR)) {
            continue;
        }

        expect(sourceCode($file))->not->toMatch('/where\(\s*[\'"]tenant_id[\'"]/');
    }
});

it('ships a provider that binds nothing', function (): void {
    $provider = sourceCode(dirname(__DIR__, 2).'/src/CustomerAccountsServiceProvider.php');

    expect($provider)
        ->not->toContain('->bind(')
        ->and($provider)->not->toContain('->singleton(')
        ->and($provider)->not->toContain('->instance(');
});

it('declares an empty extra.laravel.providers and a matching module manifest', function (): void {
    $root = dirname(__DIR__, 2);
    $composer = json_decode((string) file_get_contents($root.'/composer.json'), true);
    $module = json_decode((string) file_get_contents($root.'/module.json'), true);

    expect($composer['extra']['laravel']['providers'] ?? [])->toBe([])
        ->and($composer['version'])->toBe($module['version'])
        ->and($composer['extra']['liberu']['name'])->toBe($module['name'])
        ->and($module['requires']['packages'])->toBe([])
        ->and(class_exists($module['provider']))->toBeTrue();
});
