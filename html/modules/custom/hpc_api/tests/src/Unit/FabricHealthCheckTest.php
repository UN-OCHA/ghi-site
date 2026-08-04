<?php

namespace Drupal\Tests\hpc_api\Unit;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\State\StateInterface;
use Drupal\hpc_api\FabricHealthCheck;
use Drupal\hpc_api\Query\FabricClient;
use Drupal\Tests\UnitTestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;

/**
 * @covers \Drupal\hpc_api\FabricHealthCheck
 *
 * @group HPC API
 */
class FabricHealthCheckTest extends UnitTestCase {

  use ProphecyTrait;

  /**
   * Tests that a current result avoids another Fabric request.
   */
  public function testCurrentResultAvoidsFabricRequest(): void {
    $fabric_client = $this->prophesize(FabricClient::class);
    $fabric_client->disableCache()->shouldNotBeCalled();
    $fabric_client->query(Argument::cetera())->shouldNotBeCalled();
    $state = $this->prophesize(StateInterface::class);
    $state->get('hpc_api.fabric_health_check', [])->willReturn([
      'available' => TRUE,
      'checked' => 1000,
    ]);
    $lock = $this->prophesize(LockBackendInterface::class);

    $health_check = new FabricHealthCheck($fabric_client->reveal(), $state->reveal(), $lock->reveal(), $this->mockTime(1030));

    $this->assertTrue($health_check->isAvailable());
  }

  /**
   * Tests that a live Fabric result is stored for subsequent requests.
   */
  public function testLiveResultIsStored(): void {
    $fabric_client = $this->prophesize(FabricClient::class);
    $fabric_client->disableCache()->shouldBeCalledOnce();
    $fabric_client->query('__typename', Argument::type('null'))->willReturn((object) ['__typename' => 'Query']);
    $state = $this->prophesize(StateInterface::class);
    $state->get('hpc_api.fabric_health_check', [])->willReturn([]);
    $state->set('hpc_api.fabric_health_check', [
      'available' => TRUE,
      'checked' => 1100,
    ])->shouldBeCalledOnce();
    $lock = $this->prophesize(LockBackendInterface::class);
    $lock->acquire('hpc_api.fabric_health_check', 60)->willReturn(TRUE);
    $lock->release('hpc_api.fabric_health_check')->shouldBeCalledOnce();

    $health_check = new FabricHealthCheck($fabric_client->reveal(), $state->reveal(), $lock->reveal(), $this->mockTime(1100));

    $this->assertTrue($health_check->isAvailable());
  }

  /**
   * Tests that concurrent probes reuse the result produced under the lock.
   */
  public function testConcurrentProbeWaitsForStoredResult(): void {
    $fabric_client = $this->prophesize(FabricClient::class);
    $fabric_client->disableCache()->shouldNotBeCalled();
    $fabric_client->query(Argument::cetera())->shouldNotBeCalled();
    $state = $this->prophesize(StateInterface::class);
    $state->get('hpc_api.fabric_health_check', [])->willReturn([], [
      'available' => TRUE,
      'checked' => 1200,
    ]);
    $lock = $this->prophesize(LockBackendInterface::class);
    $lock->acquire('hpc_api.fabric_health_check', 60)->willReturn(FALSE);
    $lock->wait('hpc_api.fabric_health_check', 5)->shouldBeCalledOnce();

    $health_check = new FabricHealthCheck($fabric_client->reveal(), $state->reveal(), $lock->reveal(), $this->mockTime(1200));

    $this->assertTrue($health_check->isAvailable());
  }

  /**
   * Mocks the request time.
   */
  private function mockTime(int $request_time): TimeInterface {
    $time = $this->prophesize(TimeInterface::class);
    $time->getRequestTime()->willReturn($request_time);
    return $time->reveal();
  }

}
