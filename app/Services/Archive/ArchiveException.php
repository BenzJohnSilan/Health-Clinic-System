<?php

namespace App\Services\Archive;

use RuntimeException;

/**
 * A business-rule refusal from the Archive feature (not a bug). The message
 * is safe to show to the Admin as-is.
 */
class ArchiveException extends RuntimeException
{
}
