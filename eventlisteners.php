<?php

namespace Multitenancy\EventListeners;

use SplitPHP\System;
use SplitPHP\Execution;
use SplitPHP\Utils;
use SplitPHP\EventListener;
use SplitPHP\Database\Database;
use SplitPHP\Database\Dbmetadata;
use SplitPHP\Exceptions\NotFound;
use Exception;

class Multitenancy extends EventListener
{
  public function init(): void
  {
    require_once CORE_PATH . '/database/' . Database::getRdbmsName() . '/class.dbmetadata.php';

    $this->addEventListener('request.before', function ($evt) {
      // Exclude Logs and API Docs from multitenancy:
      if ($_SERVER['REQUEST_URI'] == '/') return;

      $req = $evt->info();
      $reqArgs = $req->getBody();

      if (!empty($reqArgs['tenant_key'])) {
        $tenant = $this->getService('multitenancy/tenant')->get($reqArgs['tenant_key']);
        $req->unsetBody('tenant_key');
      } else {
        $tenant = $this->getService('multitenancy/tenant')->detect();

        // Handle IAM reset pass for multitenancy:
        if (empty(getenv('RESETPASS_URL')))
          define('RESETPASS_URL', "https://" . TENANT_HOST . "/reset-password");
      }

      if (empty($tenant)) throw new NotFound("It was not possible to identify the tenant with provided key.");

      define('TENANT_KEY', $tenant->ds_key);
      define('TENANT_NAME', $tenant->ds_name);

      // Change database connections to point to tenant's database:
      Database::setName($tenant->ds_database_name);
    });

    $this->addEventListener('command.before', function ($evt) {
      $execution = $evt->info();

      $ignoreList = [
        'server:start',
        'server:stop',
        'setup',
        'help',
        'generate:cli',
        'generate:migration',
        'generate:seed',
        'generate:webservice',
      ];

      $fullCommand = $execution->getFullCmd();
      if (in_array($fullCommand, $ignoreList)) {
        return;
      }

      $module = $execution->getArgs()['--module'] ?? null;

      if ($module == 'multitenancy' || !Dbmetadata::tableExists('MTN_TENANT')) return;

      $evt->stopPropagation();

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

        $newExecution = new Execution(['console', $fullCommand, ...$execution->getArgs()]);
        System::runCommand($newExecution);

        unset($newExecution);
      }
    });
  }
}
