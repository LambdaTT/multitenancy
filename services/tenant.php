<?php

namespace application\services\multitenancy;

use \engine\Service;

class Tenant extends Service
{
  private static $tenant = null;

  public function detect()
  {
    // Find Tenant ID from origin's request:
    $origin = isset($_SERVER['HTTP_TENANT_DOMAIN']) ? $_SERVER['HTTP_TENANT_DOMAIN'] : parse_url($_SERVER['HTTP_ORIGIN'] ?? ($_SERVER['HTTP_REFERER'] ?? $_SERVER['HTTP_HOST']))['host'];

    $tenantDomain = str_replace('admin-', '', $origin);
    $tenantDomain = str_replace('.sindiapp.app.br', '', $tenantDomain);

    // With tenant's domain, retrieve it from database):
    return $this->get($tenantDomain);
  }

  public function get($tenantDomain)
  {
    if (empty(self::$tenant)) {
      self::$tenant = $this->getDao('SND_TENANT')
        ->filter('ds_app_domain')->equalsTo($tenantDomain)
        ->first();
    }

    return self::$tenant;
  }
}
