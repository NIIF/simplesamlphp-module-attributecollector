<?php

/**
 * Filter to collect attributes from diferent sources.
 */
class sspmod_attributecollector_Auth_Process_AttributeCollector extends SimpleSAML_Auth_ProcessingFilter {

	private $existing = 'ignore';
	private $collector = NULL;
	private $uidfield = NULL;


	/**
	 * Get and initialize the configured collector
	 *
	 * @param array $config	 Configuration information about this filter.
	 */
	private function getCollector($config) {
		if (!array_key_exists("collector", $config) || !array_key_exists("class", $config["collector"])) {
			throw new Exception('No collector class specified in configuration');
		}
		$collectorConfig = $config["collector"];
		$collectorClassName = SimpleSAML\Module::resolveClass($collectorConfig['class'], 'Collector', 'sspmod_attributecollector_SimpleCollector');
		unset($collectorConfig['class']);
		return new $collectorClassName($collectorConfig);
	}

	/**
	 * Initialize this filter.
	 *
	 * @param array $config	 Configuration information about this filter.
	 * @param mixed $reserved  For future use.
	 */
	public function __construct($config, $reserved) {
		parent::__construct($config, $reserved);

		assert('is_array($config)');

		if (!array_key_exists("uidfield", $config)) {
			throw new Exception('No uidfield specified in configuration');
		}
		$this->uidfield = $config["uidfield"];
		$this->collector = $this->getCollector($config);
		if (array_key_exists("existing", $config)) {
			$this->existing = $config["existing"];
		}
	}


	/**
	 * Apply filter expand attributes with collected ones
	 *
	 * @param array &$request  The current request
	 */
	public function process(&$request) {
		assert('is_array($request)');
		assert('array_key_exists("Attributes", $request)');

		if (array_key_exists($this->uidfield, $request['Attributes'])) {

			$newAttributes = $this->collector->getAttributes($request['Attributes'], $this->uidfield);

			if (is_array($newAttributes)) {
				$attributes =& $request['Attributes'];

				foreach($newAttributes as $name => $values) {
					if (!is_array($values)) {
						$values = array($values);
					}
					if (!array_key_exists($name, $attributes) || $this->existing === 'replace') {
						$attributes[$name] = $values;
					} else {
						if ($this->existing === 'merge') {
							$attributes[$name] = array_merge($attributes[$name], $values);
						}
					}
				}
			}
		}
	}
}

?>
