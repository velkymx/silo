<?php

namespace App\Services\Http;

use RuntimeException;

/**
 * Thrown when a URL fails the SSRF guard (bad scheme, or resolves to a
 * private/reserved address). Used to abort a request mid-redirect.
 */
class UnsafeUrlException extends RuntimeException {}
