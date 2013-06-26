<?php

class Aoe_Import_Model_ProcessorManager extends Mage_Core_Model_Abstract {

	protected $processorConfigurations = array();

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
				$this->registerProcessorConfiguration($importKey, $pathFilter, $nodeType, $processorConf);
			}
		}
	}

	public function findProcessors($importKey, $path, $nodeType=XMLReader::ELEMENT) {

	}

	public function getProcessorConfigurations() {
		return $this->processorConfigurations;
	}

	/**
	 * Register processor configuration
	 *
	 * @param $importKey
	 * @param $pathFilter
	 * @param $nodeType
	 * @param Mage_Core_Model_Config_Element $configuration
	 */
	public function registerProcessorConfiguration($importKey, $pathFilter, $nodeType, Mage_Core_Model_Config_Element $configuration) {
		$this->processorConfigurations[$importKey][$pathFilter][$nodeType][] = $configuration;
	}

}