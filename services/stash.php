<?php

namespace Multitenancy\Services;

use SplitPHP\Service;

/**
 * Stash service for managing tenant-specific data storage.
 * This service allows storing and retrieving key-value pairs in a JSON file
 * specific to each tenant, identified by the TENANT_KEY constant.
 *
 * All mutating operations (set, increment) use LOCK_EX for atomic read-modify-write.
 * get() uses a non-blocking LOCK_SH attempt to avoid starving writers when many
 * concurrent watchers are polling every 100 ms.
 */
class Stash extends Service
{
  /**
   * Path to the stash file for the current tenant.
   * @var string $STASH_PATH
   */
  private static string $STASH_PATH;

  public function __construct()
  {
    self::$STASH_PATH = dirname(__DIR__, 3) . '/cache/multitenancy/stash/' . Tenant::getKey() . '.json';

    if (!is_dir(dirname(self::$STASH_PATH))) {
      mkdir(dirname(self::$STASH_PATH), 0755, true);
    }

    if (!file_exists(self::$STASH_PATH)) {
      file_put_contents(self::$STASH_PATH, json_encode([]));
    }
  }

  /**
   * Retrieves a value from the stash by its key.
   * Uses a non-blocking shared lock attempt so that a flood of concurrent
   * readers (waitFor loops polling every 100 ms) does not starve the
   * exclusive lock needed by set() / increment().
   *
   * @param string|null $key
   * @param mixed       $default
   * @return mixed
   */
  public function get(?string $key = null, $default = null)
  {
    $fp = fopen(self::$STASH_PATH, 'r');

    // Try non-blocking shared lock; if it fails (writer holds LOCK_EX),
    // skip this read cycle — the waitFor loop will retry in 100 ms anyway.
    if (!flock($fp, LOCK_SH | LOCK_NB)) {
      fclose($fp);
      return $key === null ? [] : ($default ?? null);
    }

    $stash = json_decode(stream_get_contents($fp), true) ?? [];
    flock($fp, LOCK_UN);
    fclose($fp);

    $expiries = $stash['__expiries'] ?? [];

    if ($key === null) {
      // Filter out expired keys when fetching all
      foreach ($expiries as $k => $expTime) {
        if (time() >= $expTime) {
          unset($stash[$k]);
        }
      }
      unset($stash['__expiries']);
      return $stash;
    }

    if (isset($expiries[$key]) && time() >= $expiries[$key]) {
      return $default;
    }

    return $stash[$key] ?? ($default ?? null);
  }

  /**
   * Stores a value in the stash under the given key.
   * Uses an exclusive lock so concurrent writes do not corrupt each other.
   *
   * @param string $key
   * @param mixed  $value
   * @param int|null $ttl  Time to live in seconds. Defaults to null (indefinite).
   */
  public function set(string $key, $value, ?int $ttl = null): void
  {
    $fp = fopen(self::$STASH_PATH, 'c+');
    flock($fp, LOCK_EX);

    $stash = json_decode(stream_get_contents($fp), true) ?? [];
    $stash[$key] = $value;

    if ($ttl !== null) {
      if (!isset($stash['__expiries'])) $stash['__expiries'] = [];
      $stash['__expiries'][$key] = time() + $ttl;
    } else {
      unset($stash['__expiries'][$key]);
    }

    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, json_encode($stash));

    flock($fp, LOCK_UN);
    fclose($fp);
  }

  /**
   * Atomically increments an integer key inside a single LOCK_EX section.
   * This is the safe way to signal watchers: both the read of the current
   * value and the write of the incremented value happen under the same lock,
   * so no two concurrent signal calls can produce the same version number.
   *
   * @param string $key
   * @return int  The new (incremented) value.
   */
  public function increment(string $key): int
  {
    $fp = fopen(self::$STASH_PATH, 'c+');
    flock($fp, LOCK_EX);

    $stash   = json_decode(stream_get_contents($fp), true) ?? [];
    $newVal  = ((int) ($stash[$key] ?? 0)) + 1;
    $stash[$key] = $newVal;

    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, json_encode($stash));

    flock($fp, LOCK_UN);
    fclose($fp);

    return $newVal;
  }
}
