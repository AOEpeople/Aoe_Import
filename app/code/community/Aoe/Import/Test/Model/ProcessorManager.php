<?php

class Aoe_Import_Test_Model_ProcessorManager extends EcomDev_PHPUnit_Test_Case {

	/**
	 * TODO: the configuration should be a fixture
	 */
	public function test_loadDummyConfiguration() {
		$processorManager = Mage::getModel('aoe_import/processorManager'); /* @var $processorManager Aoe_Import_Model_ProcessorManager */
		$this->assertInstanceOf('Aoe_Import_Model_ProcessorManager', $processorManager);

		$processorManager->loadFromConfig();

		$configuration = $processorManager->getProcessorConfigurations();

		$this->assertTrue(isset($configuration['dummyImportKeyA']));
		$this->assertTrue(isset($configuration['dummyImportKeyB']));

		$this->assertTrue(isset($configuration['dummyImportKeyA']['+^//a/a+']));
		$this->assertTrue(isset($configuration['dummyImportKeyA']['+^//a/b+']));
		$this->assertTrue(isset($configuration['dummyImportKeyB']['+^//a/c+']));

		$this->assertTrue(isset($configuration['dummyImportKeyA']['+^//a/a+'][XMLReader::ELEMENT]));
		$this->assertTrue(isset($configuration['dummyImportKeyA']['+^//a/b+'][XMLReader::ELEMENT]));
		$this->assertTrue(isset($configuration['dummyImportKeyB']['+^//a/c+'][XMLReader::ELEMENT]));

		$this->assertInstanceOf('Mage_Core_Model_Config_Element', $configuration['dummyImportKeyA']['+^//a/a+'][XMLReader::ELEMENT][0]);
		$this->assertInstanceOf('Mage_Core_Model_Config_Element', $configuration['dummyImportKeyA']['+^//a/b+'][XMLReader::ELEMENT][0]);
		$this->assertInstanceOf('Mage_Core_Model_Config_Element', $configuration['dummyImportKeyB']['+^//a/c+'][XMLReader::ELEMENT][0]);
	}


}