<?php
namespace App\Services;

use RuntimeException;

/** Safe HTTP status metadata only; never stores API response bodies. */
final class PterohubRemoteHttpException extends RuntimeException
{
    public function __construct(public readonly int $httpStatus, string $method)
    {
        parent::__construct('Pterodactyl '.$method.' request failed with HTTP '.$httpStatus.'.');
    }
}
