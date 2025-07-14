<?php

namespace Multitenancy\EventListeners;

use SplitPHP\System;
use SplitPHP\Utils;
use SplitPHP\EventListener;
use SplitPHP\Database\Database;
use SplitPHP\Database\Dbmetadata;
use Exception;

class Multitenancy extends EventListener
{
  public function init(): void
  {
    require_once CORE_PATH . '/database/' . Database::getRdbmsName() . '/class.dbmetadata.php';

    $this->addEventListener('request.before', function ($evt) {
      // Exclude Logs and API Docs from multitenancy:
      if (preg_match('/^\/log(?:$|\/.*)$/', $_SERVER['REQUEST_URI']) || $_SERVER['REQUEST_URI'] == '/') return;

      $reqArgs = $evt->info()->getBody();

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
      Database::setName($tenant->ds_database_name);
    });

    $this->addEventListener('command.before', function ($evt) {
      if (!Dbmetadata::tableExists('MTN_TENANT')) return;

      $evt->stopPropagation();
      $execution = $evt->info();

      if (array_key_exists('--tenant-key', $execution->getArgs())) {
        $tenantKey = $execution->getArgs()['--tenant-key'];
        $tenants = [$this->getService('multitenancy/tenant')->get($tenantKey)];
      } else {
        $tenants = $this->getService('multitenancy/tenant')->list();
      }

      if (empty($tenants)) {
        throw new Exception("No tenants found. Please create at least one tenant before running this command.");
      }

      foreach ($tenants as $t) {
        Utils::printLn();
        Utils::printLn("\033[35m[MODULE MULTITENANCY]: Executing command for tenant: \033[32m'{$t->ds_name} ({$t->ds_key})'\033[0m");
        Utils::printLn();
        Database::setName($t->ds_database_name);

        System::runCommand($execution);
      }
    });
  }
}
