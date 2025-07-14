<?php

namespace Multitenancy\Services;

use SplitPHP\Service;

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
    // Find Tenant ID from origin's request:
    $origin = isset($_SERVER['HTTP_TENANT_DOMAIN']) ? $_SERVER['HTTP_TENANT_DOMAIN'] : parse_url($_SERVER['HTTP_ORIGIN'] ?? ($_SERVER['HTTP_REFERER'] ?? $_SERVER['HTTP_HOST']))['host'];

    $tenantKey = str_replace('admin-', '', $origin);
    $tenantKey = str_replace('.sindiapp.app.br', '', $tenantKey);

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
