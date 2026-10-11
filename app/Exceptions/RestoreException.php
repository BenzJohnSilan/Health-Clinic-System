<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A restore problem whose message is SAFE to show to the Admin: it is written
 * by us and never contains credentials, SQL or server paths. Technical detail
 * lives in getPrevious() and goes to the server log only.
 */
class RestoreException extends RuntimeException
{
}
