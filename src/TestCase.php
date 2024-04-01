<?php

namespace Michalholubec\Tester;

use Doctrine\DBAL\Connection;
use Michalholubec\Tester\Exceptions\TesterException;
use Mockery\MockInterface;
use Nette\Application\IPresenter;
use Nette\Application\IPresenterFactory;
use Nette\Application\IResponse;
use Nette\Application\PresenterFactory;
use Nette\Application\Request;
use Nette\Database\Context;
use Nette\DI\Container;
use Nette\Security\IAuthenticator;
use Phinx\Config\Config;
use Phinx\Db\Adapter\AdapterFactory;
use Phinx\Migration\Manager;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\NullOutput;
use Tester\Assert;
use Tester\TestCase as NetteTestCase;

abstract class TestCase extends NetteTestCase
{
	/** @var Container */
	protected $container;

	/** @var Manager */
	private $phinxManager;

	/** @var Login|null */
	private $login;

	public function __construct(Container $container)
	{
		$this->container = $container;
	}

	/**
	 * @return array<string>
	 */
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
		$type = $this->container->findByType($class);

		if (count($type) === 0) {
			throw new TesterException('Unknown service to mock');
		}

		$mock = \Mockery::mock(get_class($this->container->getService($type[0])));

		$this->container->removeService($type[0]);
		$this->container->addService($type[0], $mock);

		return $mock;
	}

	protected function tearDown(): void
	{
		\Mockery::close();
	}

	/**
	 * @template TClass
	 * @param class-string<TClass> $class
	 * @return TClass
	 */
	protected function getByType(string $class)
	{
		return $this->container->getByType($class);
	}

	/**
	 * @return static
	 */
	protected function logAs(string $username, string $password): TestCase
	{
		$this->login = new Login($username, $password);

		return $this;
	}

	private function processLogin(IPresenter $presenter, IAuthenticator $authenticator): void
	{
		$presenter->getUser()->login(
			$authenticator->authenticate(
				[$this->login->getUsername(), $this->login->getPassword()]
			)
		);
	}

	private function initPresenterByClass(string $class): IPresenter
	{
		$factory = $this->presenterFactory();

		$presenter = $factory->createPresenter($this->unformatPresenterClass($class));
		$presenter->autoCanonicalize = false;

		return $presenter;
	}

	private function unformatPresenterClass(string $class): ?string
	{
		$factory = $this->presenterFactory();

		$originalErrorReporting = error_reporting();

		error_reporting($originalErrorReporting & ~E_USER_DEPRECATED);

		$result = $factory->unformatPresenterClass($class);

		error_reporting($originalErrorReporting);

		return $result;
	}

	protected function get(string $presenter, string $action, array $params = []): Response
	{
		$instance = $this->initPresenterByClass($presenter);

		if ($this->login !== null) {
			$this->processLogin($instance, $this->getAuthenticator());
		}

		$params = array_merge(['action' => $action], $params);

		$request = new Request(
			$this->unformatPresenterClass($presenter),
			'GET',
			$params
		);

		return new Response($instance->run($request));
	}

	protected function post(string $presenter, string $action, array $params = [], array $post = [], array $files = []): Response
	{
		$instance = $this->initPresenterByClass($presenter);

		if ($this->login !== null) {
			$this->processLogin($instance, $this->getAuthenticator());
		}

		$params = array_merge(['action' => $action], $params);

		$request = new \Nette\Application\Request(
			$this->presenterFactory()->unformatPresenterClass($presenter),
			'POST',
			$params,
			$post,
			$files
		);

		return new Response($instance->run($request));
	}

	protected function getAuthenticator(): IAuthenticator
	{
		throw new TesterException('Authenticator is not set');
	}

	private function presenterFactory(): PresenterFactory
	{
		return $this->getByType(IPresenterFactory::class);
	}

	public function assertDatabaseCount(string $table, int $count): void
	{
		$result = $this->getByType(Context::class)->table($table)->count();

		Assert::equal($count, $result);
	}

	public function assertDatabaseEmpty(string $table): void
	{
		$this->assertDatabaseCount($table, 0);
	}

	public function assertDatabaseHas(string $table, array $data): void
	{
		$count = $this->getByType(Context::class)->table($table)->where($data)->count();

		Assert::notEqual(0, $count);
	}
}