<?php

class Aoe_Import_Test_Model_ProcessorManager extends EcomDev_PHPUnit_Test_Case {

	/**
	 * @var Aoe_Import_Model_ProcessorManager
	 */
	protected $processorManager;

	public function setUp() {
		$this->processorManager = Mage::getModel('aoe_import/processorManager');
		$this->processorManager->loadFromConfig();
	}

	/**
	 * TODO: the configuration should be a fixture
	 */
	public function test_loadDummyConfiguration() {

		$configuration = $this->processorManager->getProcessorConfigurations();

		$this->assertTrue(isset($configuration['dummyImportKeyA']));
		$this->assertTrue(isset($configuration['dummyImportKeyB']));

		$this->assertTrue(isset($configuration['dummyImportKeyA'][XMLReader::ELEMENT]));
		$this->assertTrue(isset($configuration['dummyImportKeyB'][XMLReader::ELEMENT]));

		$this->assertTrue(isset($configuration['dummyImportKeyA'][XMLReader::ELEMENT]['+^//a/a+']));
		$this->assertTrue(isset($configuration['dummyImportKeyA'][XMLReader::ELEMENT]['+^//a/b+']));
		$this->assertTrue(isset($configuration['dummyImportKeyB'][XMLReader::ELEMENT]['+^//a/c+']));

		$this->assertInstanceOf('Mage_Core_Model_Config_Element', $configuration['dummyImportKeyA'][XMLReader::ELEMENT]['+^//a/a+']['dummyProcessor1']);
		$this->assertInstanceOf('Mage_Core_Model_Config_Element', $configuration['dummyImportKeyA'][XMLReader::ELEMENT]['+^//a/b+']['dummyProcessor2']);
		$this->assertInstanceOf('Mage_Core_Model_Config_Element', $configuration['dummyImportKeyB'][XMLReader::ELEMENT]['+^//a/c+']['dummyProcessor3']);
	}

	public function test_findConfiguration() {
		$processors = $this->processorManager->findProcessors('dummyImportKeyA', '//a/a', XMLReader::ELEMENT);
		$this->assertEquals(1, count($processors));
		$this->assertEquals('dummyProcessor1', reset(array_keys($processors)));
	}

	public function test_checkPriority() {
		$processors = $this->processorManager->findProcessors('dummyImportKeyB', '//a/c', XMLReader::ELEMENT);
		$this->assertEquals(2, count($processors));
		$this->assertEquals(array('dummyProcessor4', 'dummyProcessor3'), array_keys($processors));
	}

	public function test_checkProcessorClassnames() {
		$processors = $this->processorManager->findProcessors('dummyImportKeyB', '//a/c', XMLReader::ELEMENT);
		foreach ($processors as $processor) {
			$this->assertInstanceOf('Aoe_Import_Model_Processor_Interface', $processor);
			$this->assertInstanceOf('Aoe_Import_Model_Processor_Dummy', $processor);
		}
	}


}