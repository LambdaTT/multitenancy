<?php

namespace Multitenancy\Services;

use Exception;
use SplitPHP\Database\Database;
use SplitPHP\Service;
use SplitPHP\Exceptions\BadRequest;

class Tenant extends Service
{
  private static ?object $tenant = null;

  public function list($params = [])
  {
    return $this->getDao('MTN_TENANT')
      ->bindParams($params)
      ->find();
  }

  public function execPerTenant(callable $fn)
  {
    $tenants = $this->list();
    if (empty($tenants)) throw new Exception("No tenants found. Please create at least one tenant before running this.");

    foreach ($tenants as $tenant) {
      self::$tenant = $tenant;
      Database::setName($tenant->ds_database_name);
      $fn($tenant);
    }
  }

  public function detect()
  {
    // Find Tenant key from origin's request subdomain or from HTTP_TENANT_KEY header:
    $host = self::getHost();

    $hostData = explode('.', $host);
    if (empty($hostData)) throw new BadRequest("The request host does not contain a valid tenant key.");

    $tenantKey = $hostData[0];
    // With tenant's domain, retrieve it from database):
    return $this->get($tenantKey);
  }

  public function get(string $tenantKey)
  {
    self::$tenant = $this->getDao('MTN_TENANT')
      ->filter('ds_subdomain')->equalsTo($tenantKey)
      ->or('ds_customkey')->equalsTo($tenantKey)
      ->first();

    return self::$tenant;
  }

  public static function getKey()
  {
    return self::$tenant?->ds_subdomain ?? null;
  }

  public static function getCustomKey()
  {
    return self::$tenant?->ds_customkey ?? null;
  }

  public static function getName()
  {
    return self::$tenant?->ds_name ?? null;
  }

  public static function getHost()
  {
    return isset($_SERVER['HTTP_TENANT_KEY']) ? $_SERVER['HTTP_TENANT_KEY'] : parse_url($_SERVER['HTTP_ORIGIN'] ?? ($_SERVER['HTTP_REFERER'] ?? $_SERVER['HTTP_HOST']))['host'];
  }

  public static function getCurrent()
  {
    return self::$tenant;
  }
}
