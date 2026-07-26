<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Typed error codes for shared AI infrastructure failures.
 */
enum AiErrorCode: string
{
    case UnknownProvider = 'unknown_provider';
    case ProviderNotConfigured = 'provider_not_configured';
    case InvalidRequest = 'invalid_request';
    case ProviderFailed = 'provider_failed';
    case Unexpected = 'unexpected';
}
