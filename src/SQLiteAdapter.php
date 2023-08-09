<?php

namespace Michalholubec\Tester;

use Phinx\Db\Adapter\SQLiteAdapter as PhinxSQLiteAdapter;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SQLiteAdapter extends PhinxSQLiteAdapter
{
	/**
	 * @param array<mixed> $options
	 */
	public function __construct(array $options, InputInterface $input = null, OutputInterface $output = null)
	{
		parent::__construct($options, $input, $output);

		static::$supportedColumnTypes['enum'] = 'string';
		static::$supportedColumnTypes['decimal'] = 'float';
	}
}
