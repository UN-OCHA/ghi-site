<?php

namespace Drupal\Tests\hpc_api\Unit;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Cache\NullBackend;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\hpc_api\ApiObjects\ApiObjectBase;
use Drupal\hpc_api\ConfigService;
use Drupal\hpc_api\ObjectStore;
use Drupal\Tests\UnitTestCase;

/**
 * Test for the object store.
 *
 * @covers Drupal\hpc_api\ObjectStore
 */
class ObjectStoreTest extends UnitTestCase {

  /**
   * Hold test objects.
   */
  private array $objects;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $config_factory = $this->getConfigFactoryStub([
      'hpc_api.settings' => [
        'cache_lifetime' => 3600,
      ],
    ]);

    $container = new ContainerBuilder();
    $container->set('hpc_api.config', $this->prophesize(ConfigService::class)->reveal());
    $container->set('config.factory', $config_factory);
    $container->set('datetime.time', $this->prophesize(TimeInterface::class)->reveal());
    $container->set('cache.default', $this->prophesize(NullBackend::class)->reveal());
    \Drupal::setContainer($container);

    // Create some objects.
    $this->objects = [
      1 => new CustomApiObject((object) ['Id' => 1, 'Name' => 'Object #1', 'Code' => 'CODEBLUE']),
      2 => new CustomApiObject((object) ['Id' => 2, 'Name' => 'Object #2', 'Code' => 'CODEBLUE']),
      3 => new CustomApiObject((object) ['Id' => 3, 'Name' => 'Object #3', 'Code' => 'CODERED']),
      4 => new CustomApiObject((object) ['Id' => 4, 'Name' => 'Object #4', 'Code' => 'CODEBLUE']),
      5 => new CustomApiObject((object) ['Id' => 5, 'Name' => 'Object #5', 'Code' => 'CODERED']),
      6 => new CustomApiObject((object) ['Id' => 6, 'Name' => 'Object #6', 'Code' => 'CODERED']),
    ];

    drupal_static_reset();
  }

  /**
   * Test the object store.
   *
   * @group ObjectStore
   */
  public function testObjectStore() {
    $object_store = new ObjectStore();
    $storage_key = CustomApiObject::getObjectStorageKey();
    $objects = $this->objects;

    $object_store->addObject($objects[1]);
    $this->assertEquals($objects[1], $object_store->getObject(1, $storage_key));
    $this->assertEquals([1 => $objects[1]], $object_store->getObjects(['Object #1'], $storage_key, 'Name'));
    $object_store->addObject($objects[2]);
    $this->assertEquals([1 => $objects[1], 2 => $objects[2]], $object_store->getObjects(['CODEBLUE'], $storage_key, 'Code'));
    $object_store->addObjects([$objects[3], $objects[4], $objects[5], $objects[6]]);
    $this->assertCount(3, $object_store->getObjects(['CODEBLUE'], $storage_key, 'Code'));
    $this->assertCount(3, $object_store->getObjects(['CODERED'], $storage_key, 'Code'));
    $this->assertCount(3, $object_store->getObjects([1, 2, 3], $storage_key, 'Id'));
    $this->assertCount(2, $object_store->getObjects([1, 2, 3], $storage_key, 'Id', ['Code' => 'CODEBLUE']));
    $this->assertCount(1, $object_store->getObjects([1, 2, 3], $storage_key, 'Id', ['Code' => 'CODERED']));

    $this->assertEmpty($object_store->getObjectCollection(CustomApiObject::getObjectStorageKey(), 'Code', 'CODEBLUE'));
    $object_store->addObjectCollection($objects, CustomApiObject::getObjectStorageKey(), 'Code');
    $this->assertCount(3, $object_store->getObjectCollection(CustomApiObject::getObjectStorageKey(), 'Code', 'CODEBLUE'));
  }

  /**
   * Test the object store exceptions.
   *
   * @group ObjectStore
   */
  public function testObjectStoreException() {
    $object_store = new ObjectStore();
    $storage_key = CustomApiObject::getObjectStorageKey();
    $object_store->addObjects($this->objects);

    $this->expectException(\InvalidArgumentException::class);
    $object_store->getObjects([1, 2, 3], $storage_key, 'Id', ['Code' => (object) ['id' => 1]]);
  }

  /**
   * Test the object collections.
   *
   * @group ObjectStore
   */
  public function testObjectCollection() {
    $object_store = new ObjectStore();
    $storage_key = CustomApiObject::getObjectStorageKey();

    $this->assertEmpty($object_store->getObjectCollection($storage_key, 'Code', 'CODEBLUE'));
    $this->assertEmpty($object_store->getObjectCollection($storage_key, 'Code', 'CODERED'));
    $object_store->addObjectCollection($this->objects, $storage_key, 'Code');
    $this->assertCount(3, $object_store->getObjectCollection($storage_key, 'Code', 'CODEBLUE'));
    $this->assertCount(3, $object_store->getObjectCollection($storage_key, 'Code', 'CODERED'));
    $this->assertCount(3, $object_store->getObjects(['CODEBLUE'], $storage_key, 'Code'));
    $this->assertCount(3, $object_store->getObjects(['CODERED'], $storage_key, 'Code'));
  }

  /**
   * Test that the object store does not use a persistent cache backend.
   *
   * @group ObjectStore
   */
  public function testObjectStoreDoesNotUsePersistentCache() {
    $cache = $this->createMock(CacheBackendInterface::class);
    $cache->expects($this->never())->method('get');
    $cache->expects($this->never())->method('getMultiple');
    $cache->expects($this->never())->method('set');
    \Drupal::getContainer()->set('cache.default', $cache);

    $object_store = new ObjectStore();
    $storage_key = CustomApiObject::getObjectStorageKey();

    $object_store->addObjects([$this->objects[1], $this->objects[2]]);
    $object_store->addRequestedIds($storage_key, [1, 2]);

    $this->assertEquals($this->objects[1], $object_store->getObject(1, $storage_key));
    $this->assertCount(2, $object_store->getObjects(['CODEBLUE'], $storage_key, 'Code'));
    $this->assertSame([1, 2], $object_store->getRequestedIds($storage_key, 'id'));
  }

}

/**
 * Define our test object type.
 */
class CustomApiObject extends ApiObjectBase {

  /**
   * The name.
   *
   * @var string
   */
  protected string $name;

  /**
   * The code.
   *
   * @var string
   */
  protected string $code;

  /**
   * Define the properties used for storage lookups.
   */
  const LOOKUP_PROPERTIES = [
    'Id',
    'Name',
    'Code',
  ];

  /**
   * Public constructor.
   */
  public function __construct(object $data) {
    parent::__construct($data);
    $this->name = $data->Name;
    $this->code = $data->Code;
  }

}
