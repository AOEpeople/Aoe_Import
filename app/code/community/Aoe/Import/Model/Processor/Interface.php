<?php

interface Aoe_Import_Model_Processor_Interface {

    public function setPath($path);

    public function getPath();

	public function setData($data);

	public function process();

    public function run();

	public function getSummary();

	public function reset();

	public function setOptions(array $options);

	public function getOptions();

	public function getName();

}