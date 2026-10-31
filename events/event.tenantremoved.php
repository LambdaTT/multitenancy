<?php

namespace Multitenancy\Events;

use SplitPHP\Event;

/**
 * Class TenantRemoved
 *
 * This event is triggered right after a tenant has been deleted from MTN_TENANT.
 *
 * @package Multitenancy\Events
 */
class TenantRemoved extends Event
{
  /**
   * The name of the event.
   * This constant is used to identify the event type.
   */
  public const EVENT_NAME = 'multitenancy.tenant.removed';

  /**
   * @var object $tenant
   * The tenant record that was removed.
   */
  private object $tenant;

  /**
   * TenantRemoved constructor.
   *
   * @param object $tenant The tenant record that was removed.
   */
  public function __construct(object $tenant)
  {
    $this->tenant = $tenant;
  }

  /**
   * Returns a string representation of the event.
   *
   * @return string
   */
  public function __toString(): string
  {
    return 'Event: ' . self::EVENT_NAME . ' (Tenant: ' . json_encode($this->tenant) . ')';
  }

  /**
   * Returns the tenant record that was removed.
   *
   * @return object
   */
  public function info(): object
  {
    return $this->tenant;
  }
}
