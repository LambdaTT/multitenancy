<?php

namespace application\eventlisteners;

use \engine\EventListener;
use \engine\DbConnections;
use Exception;

class Multitenancy extends EventListener
{
  public function init()
  {
    $this->addEventListener('onRequest', function ($evt) {
      // Exclude Logs and API Docs from multitenancy:
      if (preg_match('/^\/log(?:$|\/.*)$/', $_SERVER['REQUEST_URI']) || $_SERVER['REQUEST_URI'] == '/') return;

      $reqArgs = $evt->info()->getArgs();

      if (!empty($reqArgs['tenant_domain'])) {
        $tenant = $this->getService('multitenancy/tenant')->get($reqArgs['tenant_domain']);
      } else {
        $tenant = $this->getService('multitenancy/tenant')->detect();
        // Handle IAM reset pass for multitenancy:
        $host = parse_url($_SERVER['HTTP_ORIGIN'] ?? ($_SERVER['HTTP_REFERER'] ?? $_SERVER['HTTP_HOST']))['host'];
        if (empty(getenv('RESETPASS_URL')))
          define('RESETPASS_URL', "https://{$host}/reset-password");
      }

      if (empty($tenant)) throw new Exception("Invalid tenant's app domain.");

      $tenantId = $tenant->ds_app_domain;
      define('TENANT_DOMAIN', $tenantId);
      define('TENANT_NAME', $tenant->ds_name);

      // Change database connections to point to tenant's database:
      DbConnections::change('main', [
        DBHOST,
        DBPORT,
        $tenant->ds_database_name,
        $tenant->ds_database_user_main,
        $tenant->ds_database_pass_main,
      ]);

      DbConnections::change('readonly', [
        DBHOST,
        DBPORT,
        $tenant->ds_database_name,
        $tenant->ds_database_user_readonly,
        $tenant->ds_database_pass_readonly,
      ]);
    });
  }
}
