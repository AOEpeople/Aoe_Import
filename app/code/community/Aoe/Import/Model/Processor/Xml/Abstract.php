<?php

abstract class Aoe_Import_Model_Processor_Xml_Abstract extends Aoe_Import_Model_Processor_Abstract
{

    /**
     * @var Aoe_Import_Model_XmlReaderWrapper
     */
    protected $xmlReader = '';

    /**
     * @var SimpleXMLElement
     */
    protected $xml;

    /**
     * Set XMLReader
     *
     * @param $xmlReader
     * @throws Exception
     * @return void
     */
    public function setData($xmlReader)
    {
        if (!$xmlReader instanceof Aoe_Import_Model_XmlReaderWrapper) {
            throw new Exception('Wrong class');
        }
        $this->xmlReader = $xmlReader;
        $this->xml = new SimpleXMLElement($xmlReader->readOuterXml());
    }

    /**
     * Reset processor object to be ready for reuse
     *
     * @return void
     */
    public function reset()
    {
        $this->xmlReader = NULL;
        $this->xml = NULL;
        $this->uid = NULL;
        parent::reset();
    }

}