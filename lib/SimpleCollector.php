<?php

/*
 *	Base class for collectors
 *
 *	If you just extend this collector without overriding any methods
 *	it will return its configuration array as attributes
 */

class sspmod_attributecollector_SimpleCollector {

	protected $attributes = array();
	protected $users = array();

	/* Initialize this collector.
	 *
	 * @param array $config	 Configuration information about this collector.
	 */

	public function __construct($config) {
		assert('is_array($config)');

		foreach($config as $name => $values) {
			if (!is_string($name)) {
				throw new Exception('Invalid attribute name: ' . var_export($name, TRUE));
			}

			if (!is_array($values)) {
				$values = array($values);
			}
			foreach($values as $value) {
				if (!is_string($value)) {
					throw new Exception('Invalid value for attribute ' . $name . ': ' .
						var_export($values, TRUE));
				}
			}

			$this->attributes[$name] = $values;
		}
	}

	/* Get collected attributes
	 *
	 * @param array $originalAttributes	 Original attributes existing before this collector has been called
	 * @param string $uidfield	Name of the field used as uid
	 * @return array  Attributes collected
	 */
	public function getAttributes($originalAttributes, $uidfield) {
		return $this->attributes;
	}

	/* Get users
	 *
	 * @return array  Users collected
	 */
	public function getAll($uidfield) {
		return array();
	}
}

?>
