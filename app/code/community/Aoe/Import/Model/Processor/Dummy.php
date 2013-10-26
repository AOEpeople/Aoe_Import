<?php

/**
 * Class Aoe_Import_Model_Processor_Dummy
 *
 * @author Fabrizio Branca
 * @since 2013-06-26
 */
class Aoe_Import_Model_Processor_Dummy extends Aoe_Import_Model_Processor_Abstract
{

    protected $xmlReader;

    protected $options = array();

    /**
     * Set data
     *
     * @param $xmlReader
     * @return void
     */
    public function setData($xmlReader)
    {
        $this->xmlReader = $xmlReader;
    }

    /**
     * Process
     *
     * @return void
     */
    public function process()
    {
        // doing nothing
    }

    /**
     * Get summary
     *
     * @return string
     */
    public function getSummary()
    {
        return 'Summary';
    }

    /**
     * Get finish summary
     *
     * @return string
     */
    public function getFinishSummary()
    {
        return 'Finish summary';
    }

    /**
     * Reset
     *
     * @return void
     */
    public function reset()
    {
        $this->xmlReader = null;
        $this->options = array();
    }

    /**
     * Set options
     *
     * @param array $options
     * @return void
     */
    public function setOptions(array $options)
    {
        $this->options = $options;
    }

    /**
     * Get name
     *
     * @return string
     */
    public function getName()
    {
        return get_class($this);
    }

    /**
     * Get options
     *
     * @return void
     */
    public function getOptions()
    {
        // TODO: Implement getOptions() method.
    }

}