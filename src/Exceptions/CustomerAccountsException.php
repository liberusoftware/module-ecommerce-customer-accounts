<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CustomerAccounts\Exceptions;

use RuntimeException;

/**
 * The base every failure here extends, so a host can catch this module without
 * catching everything.
 *
 * Note what is not here: no promoted readonly `$code`. `code`, `message`,
 * `file`, `line` and `previous` are already properties of Exception, and
 * redeclaring one readonly is a fatal at class load — which in a test run
 * surfaces as an unattributed crash rather than a failing test.
 */
abstract class CustomerAccountsException extends RuntimeException {}
