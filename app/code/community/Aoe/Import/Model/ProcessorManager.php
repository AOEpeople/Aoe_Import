<?php

class Aoe_Import_Model_ProcessorManager {

	protected $processorConfigurations = array();

	protected $matchesCache = array();

	public function loadFromConfig() {
		$conf = Mage::getConfig()->getNode('aoe_import');
		foreach ($conf->children() as $importKey => $importKeyConf) { /* @var $importKeyConf Mage_Core_Model_Config_Element */
			foreach ($importKeyConf->children() as $processorIdentifier => $processorConf) { /* @var $processorConf Mage_Core_Model_Config_Element */
				$pathFilter = (string)$processorConf->pathFilter;
				$nodeType = (string)$processorConf->nodeType;
				$tmp = constant($nodeType);
				if (!is_null($tmp)) {
					$nodeType = $tmp;
				}
				$this->registerProcessorConfiguration($processorIdentifier, $importKey, $pathFilter, $nodeType, $processorConf);
			}
		}
	}

	/**
	 * Find processors by import key, path and nodeTypes.
	 * Returns an array of processorIdentifiers sorted by priority
	 *
	 * @param $importKey
	 * @param $path
	 * @param $nodeType
	 * @return array processorIdentifiers
	 */
	public function findProcessors($importKey, $path, $nodeType) {

		if (!isset($this->matchesCache[$importKey][$nodeType][$path])) {
			$tmp = array();
			foreach ($this->processorConfigurations[$importKey][$nodeType] as $pathFilter => $processorConfigurations) {
				if (preg_match($pathFilter, $path) > 0) {
					$tmp = array_merge($tmp, $processorConfigurations);
				}
			}
			// reverse sorting by value (priority) while maintaing the keys (classname)
			$helper = Mage::helper('aoe_import'); /* @var $helper Aoe_Import_Helper_Data */
			uasort($tmp, array($helper, 'sortByPriority'));
			$this->matchesCache[$importKey][$nodeType][$path] = array_keys($tmp);
		}

		return $this->matchesCache[$importKey][$nodeType][$path];
	}

	public function getProcessorConfigurations() {
		return $this->processorConfigurations;
	}

	/**
	 * Register processor configuration
	 *
	 * @param $processorIdentifier
	 * @param $importKey
	 * @param $pathFilter
	 * @param $nodeType
	 * @param Mage_Core_Model_Config_Element $configuration
	 */
	public function registerProcessorConfiguration($processorIdentifier, $importKey, $pathFilter, $nodeType, Mage_Core_Model_Config_Element $configuration) {
		$this->processorConfigurations[$importKey][$nodeType][$pathFilter][$processorIdentifier] = $configuration;
	}

}
