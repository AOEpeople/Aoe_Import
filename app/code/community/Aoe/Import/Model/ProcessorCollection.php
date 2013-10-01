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

    protected $resetCounter = 0;

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

        // triggers creating a new MySQL connection for this fork. Otherwise MySQL will fail
        Mage::getSingleton('core/resource')->getConnection('core_write')->closeConnection();

        $i = 1;
        $count = count($this->processors);

        foreach ($this->processors as $processor) { /* @var $processor Aoe_Import_Model_Processor_Interface */
            try {
                $processor->run();

                // output to the console
                $this->message(sprintf("(#%s: %s/%s) %s",
                    ($this->resetCounter + 1),
                    $i,
                    $count,
                    $processor->getSummary()
                ));
            } catch (Exception $e) {
                // we really should never get here because exception should be handled inside the processor
                $this->message('EXCEPTION: ' . $e->getMessage());
                Mage::logException($e);
            }

            $i++;
        }
    }

    /**
     * Fork process add it to the thread pool and run it
     *
     * @param Threadi_Pool $pool
     */
    public function forkAndGo(Threadi_Pool $pool) {

        // wait until there's a free slot in the pool
        $pool->waitTillReady();
        $this->message('Starting new thread and adding it to the pool');

        // create new thread
        $thread = new Threadi_Thread_PHPThread(array($this, 'process'));
        $thread->start();

        // append it to the pool
        $pool->add($thread);

        $this->reset();
    }

    /**
     * Reset collection
     */
    public function reset() {
        $this->resetCounter++;
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