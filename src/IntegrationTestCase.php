<?php

namespace Michalholubec\Tester;

use Michalholubec\Tester\Exceptions\TesterException;
use Mockery\MockInterface;
use Nette\DI\Container;
use Tester\TestCase;

class IntegrationTestCase extends TestCase
{
	/** @var Container */
	protected $container;

	public function __construct(Container $container)
	{
		$this->container = $container;
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
}