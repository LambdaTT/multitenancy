<?php

namespace Multitenancy\EventListeners;

use Multitenancy\Services\Tenant;

use SplitPHP\System;
use SplitPHP\Execution;
use SplitPHP\Utils;
use SplitPHP\EventListener;
use SplitPHP\ObjLoader;
use SplitPHP\Database\Database;
use SplitPHP\Database\Dbmetadata;
use SplitPHP\Exceptions\NotFound;

use Exception;

class Multitenancy extends EventListener
{
  public function init(): void
  {
    if (empty(DB_CONNECT) || DB_CONNECT != 'on') return;

    require_once CORE_PATH . '/database/' . Database::getRdbmsName() . '/class.dbmetadata.php';

    $this->addEventListener('request.before', function ($evt) {
      // Exclude Logs and API Docs from multitenancy:
      if ($_SERVER['REQUEST_URI'] == '/') return;

      // Exclude routes explicitly registered by the application as not requiring a resolved tenant
      // (e.g. public endpoints that are themselves responsible for creating a tenant). Routed
      // through getService() (not a direct Tenant:: reference) because this is the very first
      // thing that runs on every request, before any module class has actually been require'd:
      if ($this->getService('multitenancy/tenant')->isRouteExempt($_SERVER['REQUEST_URI'])) return;

      $req = $evt->info();

      // 'tenant_key' é lido de $_GET (não de $req->getBody()) de propósito: este listener roda
      // no 'request.before', ANTES de Request::parseData() popular o corpo — a essa altura
      // $req->getBody() ainda está vazio. $_GET já é preenchido nativamente pelo PHP a partir da
      // query string, então é a fonte confiável aqui. Removemos a chave de $_GET para que ela não
      // vaze no corpo do handler quando parseData() rodar em seguida (que faz $body = $_GET).
      $tenantKey = $_GET['tenant_key'] ?? null;

      if (!empty($tenantKey)) {
        $tenant = $this->getService('multitenancy/tenant')->get($tenantKey);
        unset($_GET['tenant_key']);
      } else {
        $tenant = $this->getService('multitenancy/tenant')->detect();

        // Handle IAM reset pass for multitenancy:
        if (empty(getenv('RESETPASS_URL')))
          define('RESETPASS_URL', "https://" . Tenant::getHost() . "/reset-password");
      }

      if (empty($tenant)) throw new NotFound("It was not possible to identify the tenant with provided key.");

      // Change database connections to point to tenant's database:
      Database::setName($tenant->ds_database_name);
    });

    $this->addEventListener('command.before', function ($evt) {
      /** @var Execution $execution */
      $execution = $evt->info();

      $ignoreList = [
        'server:start',
        'server:status',
        'server:stop',
        'setup',
        'help',
        'generate:cli',
        'generate:migration',
        'generate:seed',
        'generate:webservice',
        // This module's own tenant-management commands always operate on the control database
        // (MTN_TENANT) — they must never be looped per-tenant like an arbitrary app/module command:
        'multitenancy:tenants:list',
        'multitenancy:tenants:create',
        'multitenancy:tenants:remove',
        'multitenancy:tenants:help',
      ];

      // Application-registered tenant-exempt commands (MULTITENANCY_EXEMPT_COMMANDS, comma-separated).
      // Lets an application mark its own CLI workers that run against the control database or a global
      // resource — and therefore must not be looped per-tenant — without this generic module having to
      // know their names. Env-based for the same reason isRouteExempt() is (see Tenant::isRouteExempt):
      // it has no module-load-order dependency.
      $appExempt = array_filter(array_map('trim', explode(',', getenv('MULTITENANCY_EXEMPT_COMMANDS') ?: '')));
      if (!empty($appExempt)) $ignoreList = array_merge($ignoreList, $appExempt);

      $fullCommand = $execution->getFullCmd();
      if (in_array($fullCommand, $ignoreList)) {
        return;
      }

      $module = $execution->getArgs()['--module'] ?? null;

      // Control-scoped modules (MULTITENANCY_CONTROL_MODULES, comma-separated): app-registered
      // modules whose migrations/seeds target the CONTROL database and therefore must run ONCE on
      // control — never looped per-tenant — exactly like this module's own 'multitenancy' migrations.
      // Same env-based registration seam as MULTITENANCY_EXEMPT_COMMANDS (no module-load-order
      // dependency), letting an app own control-DB structure without this generic module naming it.
      $controlModules = array_filter(array_map('trim', explode(',', getenv('MULTITENANCY_CONTROL_MODULES') ?: '')));
      $controlModules[] = 'multitenancy';

      if (in_array($module, $controlModules, true) || !Dbmetadata::tableExists('MTN_TENANT')) return;

      $evt->stopPropagation();

      if (array_key_exists('--tenant-key', $execution->getArgs())) {
        $tenantKey = $execution->getArgs()['--tenant-key'];
        $this->getService('multitenancy/tenant')->get($tenantKey);
      } else {
        $this->getService('multitenancy/tenant')->execPerTenant(function ($tenant) use ($execution, $fullCommand) {
          Utils::printLn();
          Utils::printLn("\033[35m[MODULE MULTITENANCY]: Executing command for tenant: \033[32m'{$tenant->ds_name} ({$tenant->ds_subdomain})'\033[0m");
          Utils::printLn();

          $newExecution = new Execution(['console', $fullCommand, ...$execution->getArgs()]);
          System::runCommand($newExecution);

          unset($newExecution);
        });
      }
    });

    // Provisions the new tenant's database right after its MTN_TENANT record is created.
    // System::runCommand() is called directly here (not via System::executeCommand()), which
    // means 'command.before' never fires for it — the per-tenant CLI loop above is bypassed
    // entirely, so migrations:apply runs exactly once, against exactly the database we just
    // switched to.
    $this->addEventListener('multitenancy.tenant.created', function ($evt) {
      $tenant = $evt->info();

      // Execution/Cli are normally only require'd on the CLI bootstrap path (System::executeCommand()).
      // This listener can also fire from a web request (e.g. self-service tenant signup), so make
      // sure they're loaded before use:
      require_once CORE_PATH . '/kernel/class.execution.php';
      require_once CORE_PATH . '/kernel/class.cli.php';

      // migrations:apply prints its progress via Utils::printLn()/echo, which is fine on the CLI
      // but would otherwise leak straight into the HTTP response body (corrupting it) when this
      // listener runs synchronously inside a web request:
      $isWeb = System::$bootType == 'web';
      if ($isWeb) ob_start();

      // Cli::__construct() (instanciada abaixo via Execution) faz ini_set('display_errors', 1)
      // incondicionalmente. Rodando dentro de uma request web, isso vaza: warnings emitidos
      // depois (ex.: o evento response.after) caem no corpo da resposta e corrompem o JSON.
      // Captura e restaura o valor em torno do provisionamento.
      $prevDisplayErrors = ini_get('display_errors');

      try {
        Utils::printLn();
        Utils::printLn("\033[35m[MODULE MULTITENANCY]: Provisioning database for new tenant: \033[32m'{$tenant->ds_name} ({$tenant->ds_subdomain})'\033[0m");
        Utils::printLn();

        Database::setName($tenant->ds_database_name);

        $execution = new Execution(['console', 'migrations:apply']);
        System::runCommand($execution);

        unset($execution);
      } finally {
        if ($isWeb) ob_end_clean();
        ini_set('display_errors', $prevDisplayErrors);
      }
    });

    // Drops the tenant's database right after its MTN_TENANT record is removed.
    $this->addEventListener('multitenancy.tenant.removed', function ($evt) {
      $tenant = $evt->info();

      $sqlBuilder = ObjLoader::load(CORE_PATH . '/database/' . Database::getRdbmsName() . '/class.sql.php');
      $sql = $sqlBuilder->dropDatabase($tenant->ds_database_name)->output(true);

      Database::getCnn('main')->runMany($sql);
    });
  }
}
