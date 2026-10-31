<?php

namespace Multitenancy\Events;

use SplitPHP\Event;

/**
 * Class TenantCreated
 *
 * This event is triggered right after a new tenant has been inserted into MTN_TENANT.
 *
 * @package Multitenancy\Events
 */
class TenantCreated extends Event
{
  /**
   * The name of the event.
   * This constant is used to identify the event type.
   */
  public const EVENT_NAME = 'multitenancy.tenant.created';

  /**
   * @var object $tenant
   * The tenant record that was created.
   */
  private object $tenant;

  /**
   * TenantCreated constructor.
   *
   * @param object $tenant The tenant record that was created.
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
   * Returns the tenant record that was created.
   *
   * @return object
   */
  public function info(): object
  {
    return $this->tenant;
  }
}
