<?php

declare(strict_types=1);

namespace App\Providers;

use App\Events\AuditEvent;
use App\Listeners\WriteAuditLog;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as BaseEventServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;

/**
 * Wires up the audit pipeline (T-013, T-015).
 *
 * Every AuditEvent goes through WriteAuditLog, which is bound to the
 * current HTTP request via `Request::capture()` so the listener
 * always has IP / user-agent context — even when the event is
 * dispatched from a background job or queue worker (where the
 * request is null and the columns fall back to null).
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

    public function boot(): void
    {
        parent::boot();

        // Bind the current request as the second argument to
        // WriteAuditLog::handle(). When no HTTP request is active
        // (artisan command, queue worker) Request::capture() returns
        // an empty Request with null IP/UA.
        Event::listen(AuditEvent::class, function (AuditEvent $event) {
            return (new WriteAuditLog())->handle($event, Request::capture());
        });
    }
}
