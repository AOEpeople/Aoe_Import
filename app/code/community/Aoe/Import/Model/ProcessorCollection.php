<?php

/**
 * Class Aoe_Import_Model_ProcessorCollection
 *
 * @author Fabrizio Branca
 * @since 2013-09-28
 */
class Aoe_Import_Model_ProcessorCollection {

    /**
     * @var array
     */
    protected $processors = array();

    protected $verbose = true;

    /**
     * @param boolean $verbose
     */
    public function setVerbose($verbose)
    {
        $this->verbose = $verbose;
    }

    /**
     * @return boolean
     */
    public function getVerbose()
    {
        return $this->verbose;
    }

    /**
     * Add callback
     *
     * @param Aoe_Import_Model_Processor_Interface $processor
     */
    public function addProcessor(Aoe_Import_Model_Processor_Interface $processor) {
        $this->processors[] = $processor;
    }

    /**
     * Get callback array (including arguments in results if already processed)
     *
     * @return array
     */
    public function getProcessors() {
        return $this->processors;
    }

    /**
     * Count callbacks
     *
     * @return int
     */
    public function count() {
        return count($this->processors);
    }

    /**
     * Process all callbacks
     */
    public function process() {

        Mage::getSingleton('core/resource')->getConnection('core_write')->closeConnection();

        foreach ($this->processors as $processor) { /* @var $processor Aoe_Import_Model_Processor_Interface */

            $this->message(sprintf('[--- Processing %s using processor "%s" ---]', $processor->getPath(), $processor->getName()));

            $processor->process();
            $message = $processor->getSummary();
            echo $message;
        }
    }

    public function reset() {
        $this->processors = array();
    }

    /**
     * Print message
     *
     * @param string $message
     * @param bool $lineBreak
     * @return void
     */
    protected function message($message, $lineBreak = true)
    {
        if ($this->getVerbose()) {
            $output = $message;
            if ($message && $lineBreak) {
                $output .= "\n";
            }
            // $this->output .= $output;
            // if ($this->echoOutput) {
                echo $output;
            // }
        }
    }

}