<?php

namespace Multitenancy\Services;

use SplitPHP\Service;

/**
 * Stash service for managing tenant-specific data storage.
 * This service allows storing and retrieving key-value pairs in a JSON file
 * specific to each tenant, identified by the TENANT_KEY constant.
 */
class Stash extends Service
{
  /**
   * Path to the stash file for the current tenant.
   * The file is stored in the 'stash' directory under the tenant's key.
   * @var string $STASH_PATH
   */
  private static string $STASH_PATH;

  /**
   * Constructor initializes the stash service.
   * It sets the path for the stash file based on the TENANT_KEY constant,
   * creates the directory if it does not exist, and loads existing data from the file.
   */
  public function __construct()
  {
    self::$STASH_PATH = dirname(__DIR__, 3) . '/cache/multitenancy/stash/' . TENANT_KEY . '.json';

    if (!is_dir(dirname(self::$STASH_PATH))) {
      mkdir(dirname(self::$STASH_PATH), 0755, true);
    }

    if (!file_exists(self::$STASH_PATH)) {
      file_put_contents(self::$STASH_PATH, json_encode([]));
    }
  }

  /**
   * Retrieves a value from the stash by its key.
   * If the key does not exist, it returns null.
   *
   * @param string|null $key The key to retrieve from the stash.
   * @return mixed The value associated with the key, or null if not found.
   */
  public function get(?string $key = null, $default = null)
  {
    $stash = json_decode(file_get_contents(self::$STASH_PATH), true);
    if ($key === null) {
      return $stash;
    }

    return $stash[$key] ?? ($default ?? null);
  }

  /**
   * Stores a value in the stash by its key.
   *
   * @param string $key The key to store the value under.
   * @param mixed $value The value to store.
   */
  public function set(string $key, $value)
  {
    $stash = json_decode(file_get_contents(self::$STASH_PATH), true);
    $stash[$key] = $value;
    file_put_contents(self::$STASH_PATH, json_encode($stash));
  }
}
