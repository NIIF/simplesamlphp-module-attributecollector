<?php

/**
 * SQL Attributes Collector
 *
 * This class implements a collector that retrieves attributes from a database.
 * It shoud word against both MySQL and PostgreSQL
 *
 * It has the following options:
 * - dsn: The DSN which should be used to connect to the database server. Check the various
 *		  database drivers in http://php.net/manual/en/pdo.drivers.php for a description of
 *		  the various DSN formats.
 * - username: The username which should be used when connecting to the database server.
 * - password: The password which should be used when connecting to the database server.
 * - query: The sql query for retrieve attributes. You can use the special :uidfield string
 *			to refer the value of the field especified as an uidfield in the processor.
 *
 *
 * Example - with PostgreSQL database:
 * <code>
 * 'collector' => array(
 *		 'class' => 'attributecollector:SQLCollector',
 *		 'dsn' => 'pgsql:host=localhost;dbname=simplesaml',
 *		 'username' => 'simplesaml',
 *		 'password' => 'secretpassword',
 *		 'query' => 'select address, phone, country from extraattributes where uid=:uidfield',
 *		 ),
 *	   ),
 *	 ),
 * </code>
 *
 * SQLCollector allows to specify several database connections which will
 * be used sequentially when a connection fails. This can be done
 * by defining each parameter by using an array.
 *
 * Example:
 *  'collector' => array(
 *          'class' => 'attributecollector:SQLCollector',
 *          'dsn' => array('oci:dbname=first',
 *                  'mysql:host=localhost;dbname=second'),
 *          'username' => array('first', 'second'),
 *          'password' => array('first', 'second'),
 *          'query' => array("SELECT sid as SUBJECT from subjects where uid=:uid",
 *                          "SELECT sid as SUBJECT from subjects where uid=:uid AND status='OK'",
 *                  ),
 *          ),
 *  ),
 */

class sspmod_attributecollector_Collector_SQLCOllector extends sspmod_attributecollector_SimpleCollector {


	/**
	 * DSN for the database.
	 */
	private $dsn;


	/**
	 * Username for the database.
	 */
	private $username;


	/**
	 * Password for the database;
	 */
	private $password;


	/**
	 * Query for retrieving attributes
	 */
	private $query;


	/**
	 * Query for retrieving all entries
	 */
	private $getAllQuery;

	/**
	 * Valid connection
	 */
	private $current;

	/**
	 * Total configured databases
	 */
	private $total;


	/**
	 * Database handle.
	 *
	 * This variable can't be serialized.
	 */
	private $db;


	/**
	 * Attribute name case.
	 *
	 * This is optional and by default is "natural"
	 */
	private $attrcase;


	/* Initialize this collector.
	 *
	 * @param array $config  Configuration information about this collector.
	 */
	public function __construct($config) {
		$this->total = 0;
		$this->current = 0;

		foreach (array('dsn', 'username', 'password', 'query', 'get_all_query') as $id) {
			if (!array_key_exists($id, $config)) {
				throw new Exception('attributecollector:SQLCollector - Missing required option \'' . $id . '\'.');
			}

			if (is_array($config[$id])) {

				// Check array size
				if ($this->total == 0) {
					$this->total = count($config[$id]);
				} elseif (count($config[$id]) != $this->total) {
					throw new Exception('attributecollector:SQLCollector - \'' . $id . '\' size != ' . $this->total);
				}

			} elseif (is_string($config[$id])) {
				// TODO: allow single values
				// when using arrays on previous fields?
				if ($this->total > 1) {
					throw new Exception('attributecollector:SQLCollector - \'' . $id . '\' is supposed to be an array.');
				}

				$config[$id] = array($config[$id]);
				$this->total = 1;
			} else {
				throw new Exception('attributecollector:SQLCollector - \'' . $id . '\' is supposed to be a string or array.');
			}
		}

		$this->dsn = $config['dsn'];
		$this->username = $config['username'];
		$this->password = $config['password'];
		$this->query = $config['query'];
		$this->getAllQuery = $config['get_all_query'];
		$this->current = 0;

		$case_options = array ("lower" => PDO::CASE_LOWER,
			"natural" => PDO::CASE_NATURAL,
			"upper" => PDO::CASE_UPPER);
		// Default is 'natural'
		$this->attrcase = $case_options["natural"];
		if (array_key_exists("attrcase", $config)) {
			$attrcase = $config["attrcase"];
			if (in_array($attrcase, array_keys($case_options))) {
				$this->attrcase = $case_options[$attrcase];
			} else {
				throw new Exception("attributecollector:SQLCollector - Wrong case value: '" . $attrcase . "'");
			}
		}
	}


	/* Get collected attributes
	 *
	 * @param array $originalAttributes      Original attributes existing before this collector has been called
	 * @param string $uidfield      Name of the field used as uid
	 * @return array  Attributes collected
	 */
	public function getAttributes($originalAttributes, $uidfield) {
		assert('array_key_exists($uidfield, $originalAttributes)');
		$db = $this->getDB();
		$st = $db->prepare($this->query[$this->current]);
		if (FALSE === $st) {
			$err = $st->errorInfo();
			$err_msg = 'attributecollector:SQLCollector - invalid query';
			if (isset($err[2])) {
				$err_msg .= ': '.$err[2];
			}
			throw new SimpleSAML_Error_Exception('attributecollector:SQLCollector - invalid query: '.$err[2]);
		}

		$res = $st->execute(array(':uidfield' => $originalAttributes[$uidfield][0]));

		if (FALSE === $res){
			$err = $st->errorInfo();
			$err_msg = 'attributecollector:SQLCollector - invalid query execution';

			if (isset($err[2])) {
				$err_msg .= ': '.$err[2];
			}
			else if (isset($err[0])) {
				$err_msg .= ': SQLSTATE['.$err[0].']';
			}
			throw new SimpleSAML_Error_Exception($err_msg);
		}

		$db_res = $st->fetchAll(PDO::FETCH_ASSOC);

		$result = array();
		foreach($db_res as $tuple) {
			foreach($tuple as $colum => $value) {
				$result[$colum][] = $value;
			}
		}
		foreach($result as $colum => $data) {
			$result[$colum] = array_unique($data);
		}

		return $result;
	}


	/**
	 * Get database handle.
	 *
	 * @return PDO|FALSE  Database handle, or FALSE if we fail to connect.
	 */
	private function getDB() {
		if ($this->db !== NULL) {
			return $this->db;
		}

		for ($i = 0; $i<$this->total; $i++) {
			$this->current = $i;
			try {
				$this->db = new PDO($this->dsn[$i], $this->username[$i], $this->password[$i]);
			} catch (PDOException $e) {
				SimpleSAML\Logger::error('attributecollector:SQLCollector - skipping ' . $this->dsn[$i] . ': ' . $e->getMessage());
				// Error connecting to i-th database
				continue;
			}
			break;
		}
		if ($this->db == NULL) {
			throw new SimpleSAML_Error_Exception('attributecollector:SQLCollector - cannot connect to any database');
		}
		$this->db->setAttribute(PDO::ATTR_CASE, $this->attrcase);
		return $this->db;
	}


	/* Get All entries
	 *
	 * @return array  entries collected
	 */
	public function getAll($uidfield) {
		$entries = array();        

		$db = $this->getDB();

		$query = $this->getAllQuery[$this->current];

		$st = $db->prepare($query);
		if (FALSE === $st) {
			$err = $st->errorInfo();
			$err_msg = 'attributecollector:SQLCollector - invalid query';
			if (isset($err[2])) {
				$err_msg .= ': '.$err[2];
			}
			throw new SimpleSAML_Error_Exception('attributecollector:SQLCollector - invalid query: '.$err[2]);
		}

		$res = $st->execute(array(':uidfield' => 'x'));

		if (FALSE === $res){
			$err = $st->errorInfo();
			$err_msg = 'attributecollector:SQLCollector - invalid query execution';

			if (isset($err[2])) {
				$err_msg .= ': '.$err[2];
			}
			else if (isset($err[0])) {
				$err_msg .= ': SQLSTATE['.$err[0].']';
			}
			throw new SimpleSAML_Error_Exception($err_msg);
		}

		$db_res = $st->fetchAll(PDO::FETCH_ASSOC);

		foreach($db_res as $entry) {
			$info = array();
			foreach($entry as $colum => $value) {
				$info[$colum][] = $value;
			}
			foreach($info as $colum => $data) {
				$info[$colum] = array_unique($data);
			}
			if (isset($info[$uidfield]) && !empty($info[$uidfield][0])) {
				$id = $info[$uidfield][0];
				$entries[$id] = $info;
			}
			else {
				$entries[] = $info;
			}
		}

		return $entries;
	}

}

?>
