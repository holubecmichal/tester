<?php

namespace Michalholubec\Tester;

use Nette\DI\Container;
use Tester\TestCase as NetteTestCase;

class TestCase extends NetteTestCase
{
	/** @var Container */
	protected $container;

	public function __construct(Container $container)
	{
		$this->container = $container;
	}
}