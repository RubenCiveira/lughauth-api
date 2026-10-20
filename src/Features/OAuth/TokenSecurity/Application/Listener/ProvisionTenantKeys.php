<?php

declare(strict_types=1);

namespace Civi\Lughauth\Features\OAuth\TokenSecurity\Application\Listener;

use Civi\Lughauth\Features\Access\Tenant\Domain\Event\TenantCreateEvent;
use Civi\Lughauth\Features\OAuth\TokenSecurity\Application\KeyRotationService;
use Civi\Lughauth\Shared\Observability\LoggerAwareTrait;

/**
 * Provisions the signing keys of a newly created tenant so it can issue tokens immediately,
 * instead of waiting for the next scheduled rotation. A failure is logged and left to that
 * scheduled run, so it never aborts the tenant creation.
 */
class ProvisionTenantKeys
{
    use LoggerAwareTrait;

    public function __construct(private readonly KeyRotationService $rotation)
    {
    }

    public function __invoke(TenantCreateEvent $event): void
    {
        $tenant = $event->payload->getName();
        try {
            $this->rotation->rotate($tenant);
        } catch (\Throwable $ex) {
            $this->logError('Cannot provision signing keys for new tenant ' . $tenant . ': ' . $ex->getMessage());
        }
    }
}
