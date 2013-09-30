<?php

abstract class Aoe_Import_Model_Processor_Xml_Abstract extends Aoe_Import_Model_Processor_Abstract
{

    /**
     * @var SimpleXMLElement
     */
    protected $xml;

    /**
     * Set XML
     *
     * @param $xml
     * @throws Exception
     * @return void
     */
    public function setData($xml)
    {
        if (!$xml instanceof SimpleXMLElement) {
            throw new Exception('Wrong class');
        }
        $this->xml = $xml;
    }

    public function getData() {
        return $this->xml;
    }

    /**
     * Reset processor object to be ready for reuse
     *
     * @return void
     */
    public function reset()
    {
        $this->xml = NULL;
        parent::reset();
    }

}