<?php

declare(strict_types=1);

namespace App\Providers;

use App\Events\AuditEvent;
use App\Listeners\WriteAuditLog;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as BaseEventServiceProvider;

/**
 * Wires up the audit pipeline (T-013, T-015).
 *
 * Auto-discovery is disabled at the framework level via
 * `Application::configure()->withEvents(discover: false)` in
 * `bootstrap/app.php`. Without that, the framework's
 * `Illuminate\Events\EventServiceProvider` would auto-discover
 * `WriteAuditLog@handle` for `AuditEvent` and add it as a second
 * listener entry, causing every audit log row to be written twice.
 */
class EventServiceProvider extends BaseEventServiceProvider
{
    /**
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        AuditEvent::class => [
            WriteAuditLog::class,
        ],
    ];
}

