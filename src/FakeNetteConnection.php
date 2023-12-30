<?php
namespace Michalholubec\Tester;

use Nette;
use Nette\Database\DriverException;
use Nette\Database\Drivers\SqliteDriver;
use Nette\Database\ISupplementalDriver;
use Nette\Database\ResultSet;
use Nette\Database\Row;
use Nette\Database\SqlLiteral;
use Nette\Database\SqlPreprocessor;
use PDO;
use PDOException;


/**
 * Represents a connection between PHP and a database server.
 */
class FakeNetteConnection extends Nette\Database\Connection
{
	use Nette\SmartObject;

	/** @var callable[]  function (Connection $connection): void; Occurs after connection is established */
	public $onConnect;

	/** @var callable[]  function (Connection $connection, ResultSet|DriverException $result): void; Occurs after query is executed */
	public $onQuery;

	/** @var array */
	private $params;

	/** @var array */
	private $options;

	/** @var ISupplementalDriver */
	private $driver;

	/** @var SqlPreprocessor */
	private $preprocessor;

	/** @var PDO */
	private $pdo;

	/** @var string|null */
	private $sql;

	private $wrappedPdo;

	public function __construct($wrappedPdo, string $dsn, string $user = null, $password = null, array $options = null)
	{
		$this->wrappedPdo = $wrappedPdo;

		$this->params = array($dsn, $user, $password);
		$this->options = (array) $options;

		if (empty($options['lazy'])) {
			$this->connect();
		}
	}


	/** @return void */
	public function connect(): void
	{
		if ($this->pdo) {
			return;
		}

		$this->pdo = $this->wrappedPdo;

		$this->driver = new SqliteDriver();

		$this->preprocessor = new SqlPreprocessor($this);
		$this->onConnect($this);
	}


	/** @return void */
	public function reconnect(): void
	{
		$this->disconnect();
		$this->connect();
	}


	/** @return void */
	public function disconnect(): void
	{
		$this->pdo = null;
	}


	/** @return string */
	public function getDsn(): string
	{
		return $this->params[0];
	}


	/** @return PDO */
	public function getPdo(): PDO
	{
		$this->connect();
		return $this->pdo;
	}


	/** @return Nette\Database\ISupplementalDriver */
	public function getSupplementalDriver(): ISupplementalDriver
	{
		$this->connect();
		return $this->driver;
	}


	/**
	 * @param  string  sequence object
	 * @return string
	 */
	public function getInsertId(string $sequence = null): string
	{
		try {
			$res = $this->getPdo()->lastInsertId($sequence);
			return $res === false ? '0' : $res;
		} catch (PDOException $e) {
			throw $this->driver->convertException($e);
		}
	}


	/**
	 * @param  string  string to be quoted
	 * @param  int     data type hint
	 * @return string
	 */
	public function quote(string $string, int $type = PDO::PARAM_STR): string
	{
		try {
			$res = $this->getPdo()->quote($string, $type);
		} catch (PDOException $e) {
			throw DriverException::from($e);
		}
		if (!is_string($res)) {
			throw new DriverException('PDO driver is unable to quote string.');
		}
		return $res;
	}


	/** @return void */
	public function beginTransaction(): void
	{
		$this->query('::beginTransaction');
	}


	/** @return void */
	public function commit(): void
	{
		$this->query('::commit');
	}


	/** @return void */
	public function rollBack(): void
	{
		$this->query('::rollBack');
	}


	/**
	 * Generates and executes SQL query.
	 * @param  string
	 * @return ResultSet
	 */
	public function query(string $sql, ...$params): ResultSet
	{
		list($this->sql, $params) = $this->preprocess($sql, ...$params);
		try {
			$result = new ResultSet($this, $this->sql, $params);
		} catch (PDOException $e) {
			$this->onQuery($this, $e);
			throw $e;
		}
		$this->onQuery($this, $result);
		return $result;
	}


	/**
	 * @param  string
	 * @return ResultSet
	 */
	public function queryArgs($sql, array $params): ResultSet
	{
		return $this->query($sql, ...$params);
	}


	/**
	 * @return [string, array]
	 */
	public function preprocess(string $sql, ...$params): array
	{
		$this->connect();
		return $params
			? $this->preprocessor->process(func_get_args())
			: [$sql, []];
	}


	/**
	 * @return string|null
	 */
	public function getLastQueryString(): ?string
	{
		return $this->sql;
	}


	/********************* shortcuts ****************d*g**/


	/**
	 * Shortcut for query()->fetch()
	 * @param  string
	 * @return Row
	 */
	public function fetch(string $sql, ...$params): ?Nette\Database\IRow
	{
		return $this->query($sql, ...$params)->fetch();
	}


	/**
	 * Shortcut for query()->fetchField()
	 * @param  string
	 * @return mixed
	 */
	public function fetchField(string $sql, ...$params)
	{
		return $this->query($sql, ...$params)->fetchField();
	}


	/**
	 * Shortcut for query()->fetchFields()
	 * @param  string
	 * @return array|null
	 */
	public function fetchFields(string $sql, ...$params): ?array
	{
		return $this->query($sql, ...$params)->fetchFields();
	}


	/**
	 * Shortcut for query()->fetchPairs()
	 * @param  string
	 * @return array
	 */
	public function fetchPairs(string $sql, ...$params): array
	{
		return $this->query($sql, ...$params)->fetchPairs();
	}


	/**
	 * Shortcut for query()->fetchAll()
	 * @param  string
	 * @return array
	 */
	public function fetchAll(string $sql, ...$params): array
	{
		return $this->query($sql, ...$params)->fetchAll();
	}


	/**
	 * @return SqlLiteral
	 */
	public static function literal(string $value, ...$params): SqlLiteral
	{
		return new SqlLiteral($value, $params);
	}
}
