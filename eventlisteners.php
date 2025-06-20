<?php

namespace Multitenancy\EventListeners;

use SplitPHP\EventListener;
use SplitPHP\database\DbConnections;
use Exception;

class Multitenancy extends EventListener
{
  public function init()
  {
    $this->addEventListener('onRequest', function ($evt) {
      // Exclude Logs and API Docs from multitenancy:
      if (preg_match('/^\/log(?:$|\/.*)$/', $_SERVER['REQUEST_URI']) || $_SERVER['REQUEST_URI'] == '/') return;

      $reqArgs = $evt->info()->getArgs();

      if (!empty($reqArgs['tenant_key'])) {
        $tenant = $this->getService('multitenancy/tenant')->get($reqArgs['tenant_key']);
      } else {
        $tenant = $this->getService('multitenancy/tenant')->detect();
        // Handle IAM reset pass for multitenancy:
        $host = parse_url($_SERVER['HTTP_ORIGIN'] ?? ($_SERVER['HTTP_REFERER'] ?? $_SERVER['HTTP_HOST']))['host'];
        if (empty(getenv('RESETPASS_URL')))
          define('RESETPASS_URL', "https://{$host}/reset-password");
      }

      if (empty($tenant)) throw new Exception("It was not possible to identify the tenant with provided key.");

      define('TENANT_KEY', $tenant->ds_key);
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
