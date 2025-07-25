<?php

namespace Multitenancy\Services;

use SplitPHP\Service;
use SplitPHP\Exceptions\BadRequest;

class Tenant extends Service
{
  private static $tenant = null;

  public function list($params = [])
  {
    return $this->getDao('MTN_TENANT')
      ->bindParams($params)
      ->find();
  }

  public function detect()
  {
    // Find Tenant key from origin's request:
    define('TENANT_HOST', isset($_SERVER['HTTP_TENANT_KEY']) ? $_SERVER['HTTP_TENANT_KEY'] : parse_url($_SERVER['HTTP_ORIGIN'] ?? ($_SERVER['HTTP_REFERER'] ?? $_SERVER['HTTP_HOST']))['host']);

    $hostData = explode('.', TENANT_HOST);
    if (empty($hostData)) throw new BadRequest("The request host does not contain a valid tenant key.");

    $tenantKey = $hostData[0];
    // With tenant's domain, retrieve it from database):
    return $this->get($tenantKey);
  }

  public function get($tenantKey)
  {
    if (empty(self::$tenant)) {
      self::$tenant = $this->getDao('MTN_TENANT')
        ->filter('ds_key')->equalsTo($tenantKey)
        ->first();
    }

    return self::$tenant;
  }
}
