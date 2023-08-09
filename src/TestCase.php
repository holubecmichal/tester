<?php

namespace Michalholubec\Tester;

use Doctrine\DBAL\Connection;
use Michalholubec\Tester\Exceptions\TesterException;
use Mockery\MockInterface;
use Nette\DI\Container;
use Phinx\Config\Config;
use Phinx\Db\Adapter\AdapterFactory;
use Phinx\Migration\Manager;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\NullOutput;
use Tester\TestCase as NetteTestCase;

abstract class TestCase extends NetteTestCase
{
	/** @var Container */
	protected $container;

	/** @var Manager */
	private $phinxManager;

	public function __construct(Container $container)
	{
		$this->container = $container;
	}

	abstract protected function getMigrationPaths(): array;

	abstract protected function getSeedPath(): string;

	protected function migrate(): void
	{
		AdapterFactory::instance()->registerAdapter('sqlite', SQLiteAdapter::class);

		$connection = $this->container->getByType(Connection::class);

		$pdo = $connection->getWrappedConnection();

		$netteConnection = new FakeNetteConnection($pdo, 'sqlite::memory:', 'root');

		$this->container->removeService('database.default.connection');
		$this->container->addService('database.default.connection', $netteConnection);

		$config['environments']['test']['connection'] = $pdo;
		$config['paths']['migrations'] = $this->getMigrationPaths();
		$config['paths']['seeds'] = $this->getSeedPath();

		$this->phinxManager = new Manager(new Config($config), new StringInput(' '), new NullOutput());

		$this->phinxManager->migrate('test');
	}

	protected function seed(string $seeder): void
	{
		$this->phinxManager->seed('test', $seeder);
	}

	protected function mock(string $class): MockInterface
	{
		$mock = \Mockery::mock($class);

		$types = $this->container->findByType($class);

		if (count($types) === 0) {
			throw new TesterException('Unknown service to mock');
		}

		$this->container->removeService($types[0]);
		$this->container->addService($types[0], $mock);

		return $mock;
	}

	protected function tearDown(): void
	{
		\Mockery::close();
	}
}