<?php

/**
 * LDAP Attributes collector
 *
 * This class implements a collector that retrieves attributes from a LDAP
 * server.
 *
 * It has the following options:
 * - host: LDAP server host
 * - port: LDAP server port
 * - binddn: The username which should be used when connecting to the LDAP
 *           server.
 * - password: The password which should be used when connecting to the LDAP
 *             server.
 * - basedn:   DN to start the LDAP search
 * - attrlist: An associative array of [Final attr1 => LDAP attr1, Final attr2
 * => LDAP atr2]
 * - searchfilter: filter used to search the directory. You can use the special
 * :uidfield string to refer the value of the field specified as an uidfield in
 * the processor
 *
 * Example configuration:
 *
 * <code>
 * 'collector' => array(
 *		 'class' => 'attributecollector:LDAPCollector',
 *       'host' => 'myldap.srv',
 *		 'port' => 389,
 *		 'binddn' => 'cn=myuser',
 *		 'password' => 'yaco',
 *		 'basedn' => 'dc=my,dc=org',
 *		 'searchfilter' => 'uid=:uidfield',
 *		 'attrlist' => array(
 *			 // Final attr => LDAP attr
 *			 'myClasses' => 'objectClass',
 *           ),
 *       ),
 * </code>
 */
class sspmod_attributecollector_Collector_LDAPCollector extends sspmod_attributecollector_SimpleCollector {


	/**
	 * Host and port to connect to
	 */
	private $host;
	private $port;

	/**
	 * Ldap Protocol
	 */
	private $protocol;

	/**
	 * Bind DN and password
	 */
	private $binddn;
	private $password;

	/**
	 * Base DN to search LDAP
	 */
	private $basedn;


	/**
	 * Attribute list to retrieve. Syntax: LDAPattr1 => Realattr1
	 */
	private $attrlist;

	/**
	 * Search filter
	 */
	private $searchfilter;

	/**
	 * LDAP handler
	 */
	private $ds;


	/* Initialize this collector.
	 *
	 * @param array $config	 Configuration information about this collector.
	 */
	public function __construct($config) {

		foreach (array('host', 'port', 'basedn', 'searchfilter') as $id) {
			if (!array_key_exists($id, $config)) {
				throw new Exception('attributecollector:LDAPCollector - Missing required option \'' . $id . '\'.');
			}
			if ($id != 'port' && !is_string($config[$id])) {
				throw new Exception('attributecollector:LDAPCollector - \'' . $id . '\' is supposed to be a string.');
			}
		}
		if (array_key_exists('attrlist', $config)) {
			if (!is_array($config['attrlist'])) {
				throw new Exception('attributecollector:LDAPCollector - \'' . $id . '\' is supposed to be an associative array.');
			}
			$this->attrlist = $config['attrlist'];
		}

		$this->host = $config['host'];
		$this->port = $config['port'];
		$this->basedn = $config['basedn'];
		$this->searchfilter = $config['searchfilter'];

		if (!array_key_exists('protocol', $config)) {
			$this->protocol = 3;
		} else {
			$this->protocol = (integer)$config['protocol'];
		}

		if (array_key_exists('binddn', $config)) {
			$this->binddn = $config['binddn'];
			if (array_key_exists('password', $config)) {
				$this->password = $config['password'];
			} else {
				throw new Exception('attributecollector:LDAPCollector - binddn is specified but no password is supplied');
			}
		} else {
			$this->binddn = NULL;
		}
	}


	/* Get collected attributes
	 *
	 * @param array $originalAttributes	 Original attributes existing before this collector has been called
	 * @param string $uidfield	Name of the field used as uid
	 * @return array  Attributes collected
	 */
	public function getAttributes($originalAttributes, $uidfield) {
		assert('array_key_exists($uidfield, $originalAttributes)');

		// Bind to LDAP
		$this->bindLdap();

		$retattr = array();

		$id = $originalAttributes[$uidfield][0];

		// Prepare filter
		$filter = preg_replace('/:uidfield/', $id, 
			$this->searchfilter);

		if ($this->attrlist) {
			$fetch = array_unique(array_values($this->attrlist));
			$res = @ldap_search($this->ds, $this->basedn, $filter, $fetch);    
		}
		else {
			$res = @ldap_search($this->ds, $this->basedn, $filter);
		}

		if ($res === FALSE) {
			// Problem with LDAP search
			throw new Exception('attributecollector:LDAPCollector - LDAP Error when trying to fetch attributes');
		}

		$entry = @ldap_first_entry($this->ds, $res);
		$info = @ldap_get_attributes($this->ds, $entry);

		if ($info !== FALSE && is_array($info)) {
			$retattr = $this->parse_ldap_result($info, $this->attrlist);
		}

		return $retattr;

	}

	/**
	 * Connects and binds to the configured LDAP server. Stores LDAP
	 * handler in $this->ds
	 */
	private function bindLdap() {
		// Bind to LDAP
		$ds = ldap_connect($this->host, $this->port);
		ldap_set_option($ds, LDAP_OPT_PROTOCOL_VERSION, $this->protocol);
		if (is_null($ds)) {
			throw new Exception('attributecollector:SQLCollector - Cannot connect to LDAP');
		}

		if ($this->binddn !== NULL) {
			if (ldap_bind($ds, $this->binddn, $this->password) !== TRUE) {
				throw new Exception('attributecollector:SQLCollector - Cannot bind to LDAP');
			}
		}

		$this->ds = $ds;
	}

	/* Get All entries
	 *
	 * @return array  entries collected
	 */
	public function getAll($uidfield) {
		$entries = array();

		// Bind to LDAP
		$this->bindLdap();

		$filter = '('.$uidfield.'=*)';

		if ($this->attrlist) {
			$fetch = array_unique(array_values($this->attrlist));
			$res = ldap_search($this->ds, $this->basedn, $filter, $fetch);    
		}
		else {
			$res = ldap_search($this->ds, $this->basedn, $filter);
		}


		if ($res === FALSE) {
			// Problem with LDAP search
			throw new Exception('attributecollector:LDAPCollector - LDAP Error when trying to fetch attributes');
		}

		$info = ldap_get_entries($this->ds, $res);

		if ($info !== FALSE && is_array($info)) {
			unset($info['count']);
			foreach ($info as $entry) {
				$result = $this->parse_ldap_result($entry, $this->attrlist);

				if (isset($result[$uidfield]) && !empty($result[$uidfield][0])) {
					$id = $result[$uidfield][0];
					$entries[$id] = $result;
				} else {
					$entries[] = $result;
				}
			}
		}

		return $entries;
	}

	/**
	 * Retrieves attributes from a ldap_get_entries
     *
     * @param Array $entry LDAP result
     * @param Array $attrlist Attribute list
     * @access private
     * @return Array Collected attributes for given entry
     */
	private function parse_ldap_result($entry, $attrlist) {
		$result = array();

		// Assign values
		if (is_array($this->attrlist)) {
			// Take care of case sensitive
			$entry = array_change_key_case($entry, CASE_LOWER);
			foreach ($this->attrlist as $finalattr => $ldapattr) {
				$ldapattr_lc = strtolower($ldapattr);
				if (isset($entry[$ldapattr_lc]) &&
					$entry[$ldapattr_lc]['count'] > 0) {
						unset ($entry[$ldapattr_lc]['count']);
						$result[$finalattr] = $entry[$ldapattr_lc];
					}
			}
		} else {
			foreach ($entry as $key => $value) {
				if (!is_integer($key) && $entry[$key]['count'] > 0) {
					$result[$key] = array($value[0]);
				}
			}
		}

		return $result;
	}

}

?>
