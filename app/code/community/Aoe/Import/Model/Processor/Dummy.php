<?php

class Aoe_Import_Model_Processor_Dummy implements Aoe_Import_Model_Processor_Interface {

	protected $xmlReader;

	protected $options = array();

	public function setData($xmlReader) {
		$this->xmlReader = $xmlReader;
	}

	public function process() {
		// doing nothing
	}

	public function getSummary() {
		return 'Summary';
	}

	public function getFinishSummary() {
		return 'Finish summary';
	}

	public function reset() {
		$this->xmlReader = NULL;
		$this->options = array();
	}

	public function setOptions(array $options) {
		$this->options = $options;
	}

	public function getName() {
		return get_class($this);
	}

	public function getOptions() {
		// TODO: Implement getOptions() method.
	}


}