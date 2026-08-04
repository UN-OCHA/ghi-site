<?php

namespace Drupal\hpc_api;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\State\StateInterface;
use Drupal\hpc_api\Query\FabricClient;

/**
 * Checks live Fabric availability without amplifying upstream requests.
 */
final class FabricHealthCheck {

  private const LOCK_NAME = 'hpc_api.fabric_health_check';
  private const STATE_KEY = 'hpc_api.fabric_health_check';
  private const RESULT_TTL = 60;

  /**
   * Constructs a Fabric health check.
   */
  public function __construct(private readonly FabricClient $fabricClient, private readonly StateInterface $state, private readonly LockBackendInterface $lock, private readonly TimeInterface $time) {}

  /**
   * Checks whether Fabric accepts an uncached GraphQL request.
   */
  public function isAvailable(): bool {
    $now = $this->time->getRequestTime();
    $result = $this->state->get(self::STATE_KEY, []);
    if ($this->isCurrent($result, $now)) {
      return (bool) $result['available'];
    }

    if (!$this->lock->acquire(self::LOCK_NAME, self::RESULT_TTL)) {
      $this->lock->wait(self::LOCK_NAME, 5);
      $result = $this->state->get(self::STATE_KEY, []);
      return $this->isCurrent($result, $now) && (bool) $result['available'];
    }

    try {
      // The health check must verify live authentication, not cached data.
      $this->fabricClient->disableCache();
      $error = NULL;
      $available = $this->fabricClient->query('__typename', $error) !== FALSE;
      $this->state->set(self::STATE_KEY, [
        'available' => $available,
        'checked' => $now,
      ]);
      return $available;
    }
    finally {
      $this->lock->release(self::LOCK_NAME);
    }
  }

  /**
   * Checks whether a stored health result is still current.
   */
  private function isCurrent(mixed $result, int $now): bool {
    return is_array($result)
      && array_key_exists('available', $result)
      && isset($result['checked'])
      && is_numeric($result['checked'])
      && (int) $result['checked'] >= $now - self::RESULT_TTL;
  }

}
