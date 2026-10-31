<?php

namespace Multitenancy\Services;

use Exception;
use SplitPHP\Database\Database;
use SplitPHP\Database\Dao;
use SplitPHP\EventDispatcher;
use SplitPHP\Service;
use SplitPHP\Exceptions\BadRequest;

class Tenant extends Service
{
  private static ?object $tenant = null;

  /**
   * Checks whether the given request URI matches any of the exempt route patterns configured via
   * the MULTITENANCY_EXEMPT_ROUTES env var (comma-separated regexes, no delimiters). Used to let
   * application-level public endpoints (e.g. ones that themselves create a tenant) skip mandatory
   * tenant detection. Env-based on purpose: application bootstrap code runs (AppLoader::init())
   * before this module's own classes become loadable (ModLoader::init()), so a runtime PHP
   * registry populated by the application isn't reliably available in time — an env var has no
   * such ordering dependency.
   */
  public static function isRouteExempt(string $uri): bool
  {
    $patterns = array_filter(array_map('trim', explode(',', getenv('MULTITENANCY_EXEMPT_ROUTES') ?: '')));

    foreach ($patterns as $pattern) {
      // '#' as delimiter, not '/' — these patterns are URL paths and always contain slashes.
      if (preg_match('#^' . $pattern . '$#', $uri)) return true;
    }

    return false;
  }

  public function list($params = [])
  {
    return $this->getDao('MTN_TENANT')
      ->bindParams($params)
      ->find();
  }

  /**
   * Creates a new tenant, identified by a unique subdomain slug derived from its name.
   * Dispatches 'multitenancy.tenant.created' right after insertion, so listeners (module-level:
   * database provisioning; app-level: DNS, welcome e-mail, etc) can react to it.
   */
  public function create(array $data)
  {
    if (empty($data['ds_name'])) throw new BadRequest("O nome é obrigatório.");

    $data['ds_subdomain'] = $this->generateUniqueSubdomain($data['ds_name']);
    $data['ds_database_name'] = 'tenant_' . str_replace('-', '_', $data['ds_subdomain']);

    $tenant = $this->getDao('MTN_TENANT')->insert($data);

    // Commit the new tenant to the control database before dispatching: the module's own
    // 'multitenancy.tenant.created' listener switches the active connection to the tenant's
    // database (Database::setName) and runs migrations:apply, so the control-side insert must
    // be durable — and its transaction closed — before that context switch.
    Dao::flush();

    EventDispatcher::dispatch(fn() => true, 'multitenancy.tenant.created', [$tenant]);

    return $tenant;
  }

  /**
   * Removes a tenant, identified by its subdomain or custom key.
   * Dispatches 'multitenancy.tenant.removed' right after deletion.
   */
  public function remove(string $tenantKey)
  {
    $tenant = $this->get($tenantKey);
    if (empty($tenant)) throw new Exception("Tenant '{$tenantKey}' not found.");

    $rowsAffected = $this->getDao('MTN_TENANT')
      ->filter('id_snd_tenant')->equalsTo($tenant->id_snd_tenant)
      ->delete();

    // Commit the removal before dispatching: the 'multitenancy.tenant.removed' listener drops the
    // tenant's database, so the control-side delete must be durable before that destructive step.
    Dao::flush();

    EventDispatcher::dispatch(fn() => true, 'multitenancy.tenant.removed', [$tenant]);

    return $rowsAffected;
  }

  /**
   * Generates a unique ds_subdomain slug from a tenant's name, appending an incremental
   * numeric suffix in case of collision.
   */
  private function generateUniqueSubdomain(string $name): string
  {
    $base = $this->getService('utils/misc')->stringToSlug($name);
    $slug = $base;
    $suffix = 1;

    while (
      $this->getDao('MTN_TENANT')
        ->filter('ds_subdomain')->equalsTo($slug)
        ->first()
    ) {
      $suffix++;
      $slug = $base . '-' . $suffix;
    }

    return $slug;
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
