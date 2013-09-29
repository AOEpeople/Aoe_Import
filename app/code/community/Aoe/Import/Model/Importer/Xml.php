<?php

/**
 * XML Importer
 *
 * @author Fabrizio Branca
 * @since  2013-06-26
 */
class Aoe_Import_Model_Importer_Xml extends Aoe_Import_Model_Importer_Abstract {

    /**
     * @var Aoe_Import_Model_ProcessorManager
     */
    protected $processorManager;

    /**
     * @var string importKey
     */
    protected $importKey = 'default';

    /**
     * @var string|bool path with sibling count
     */
    protected $skippingUntil = false;

    /**
     * @var int
     */
    protected $skipCount = 0;

    protected $threadPoolSize = 1;

    /**
     * @var int select the highest possible number (to reduce threading overhead) that will successfully process all imports
     * before hitting the memory limit
     */
    protected $processorCollectionSize = 40;

    /**
     * @return Aoe_Import_Model_ProcessorManager
     */
    public function getProcessorManager() {
        if (is_null($this->processorManager)) {
            $this->processorManager = Mage::getSingleton('aoe_import/processorManager');
            $this->processorManager->loadFromConfig();
        }
        return $this->processorManager;
    }

    /**
     * Set file name
     *
     * @param $fileName
     */
    public function setFileName($fileName) {
        $this->fileName = $fileName;
    }

    /**
     * @param string $skippingUntil
     */
    public function setSkippingUntil($skippingUntil) {
        $this->skippingUntil = $skippingUntil;
    }

    /**
     * @param bool $importKey
     */
    public function setImportKey($importKey) {
        $this->importKey = $importKey;
    }

    /**
     * @param int $threadPoolSize
     */
    public function setThreadPoolSize($threadPoolSize)
    {
        $this->threadPoolSize = $threadPoolSize;
    }

    /**
     * @param int $processorCollectionSize
     */
    public function setProcessorCollectionSize($processorCollectionSize)
    {
        $this->processorCollectionSize = $processorCollectionSize;
    }



    /**
     * Import
     *
     * @throws Exception
     * @return void
     */
    protected function _import() {

        require_once Mage::getBaseDir('lib') . '/Threadi/Loader.php';

        $xmlReader = Mage::getModel('aoe_import/xmlReaderWrapper'); /* @var $xmlReader Aoe_Import_Model_XmlReaderWrapper */

        $this->message('Loading file... ', false);
        $processorReturnValue = $xmlReader->open($this->fileName);
        if ($processorReturnValue === false) { throw new Exception('Error while opening file in XMLReader'); }
        $this->message('done', true);

        $this->message('Find processors... ', false);
        if ($this->getProcessorManager()->hasProcessorsForImportKey($this->importKey) === false) {
            Mage::throwException(sprintf('No processors found for importKey "%s"', $this->importKey));
        }
        $nodeTypesWithProcessors = $this->getProcessorManager()->getRegisteredNodeTypes();
        $this->message('done', true);

        $this->message('Initializing thread pool...');
        $pool = new Threadi_Pool($this->threadPoolSize);

        $this->message('Initialize processor collection');
        $processorCollection = Mage::getModel('aoe_import/processorCollection'); /* @var $processorCollection Aoe_Import_Model_ProcessorCollection */
        $processorCollection->setVerbose($this->getVerbose());

        $this->message('Waiting for XMLReader to start...');
        while ($xmlReader->read()) {

            if ($this->wasShutDown()) {
                $this->endTime = microtime(true);
                $this->message('========================== Aborting... ==========================');
                $this->message($this->getImporterSummary());
                exit;
            }

            if ($this->skippingUntil) {
                $pathWithSiblingCount = $xmlReader->getPathWithSiblingCount();
                if ($this->skippingUntil == $pathWithSiblingCount) {
                    // finish skipping elements
                    $this->skippingUntil = false;
                } else {
                    $this->skipCount++;
                    if ($this->skipCount % 10000 == 0) {
                        $this->message(sprintf('Skipping... (Current position: %s)', $pathWithSiblingCount));
                    }
                    continue;
                }
            }

            $path = $xmlReader->getPath();
            // $this->message($path);



            if (in_array($xmlReader->nodeType, $nodeTypesWithProcessors)) {

                $processors = $this->getProcessorManager()->findProcessors($this->importKey, $path, $xmlReader->nodeType);

                foreach ($processors as $processorIdentifier => $processor) { /* @var $processor Aoe_Import_Model_Processor_Interface */

                    $processorName = $processor->getName();

                    $path = $xmlReader->getPathWithSiblingCount();

                    // profiling
                    if ($this->profilerOutput) {
                        $startTime = microtime(true);
                        $startMemory = memory_get_usage(true);
                    }

                    try {

                        if ($processorCollection->count() >= $this->processorCollectionSize) {
                            $pool->waitTillReady();
                            $this->message('Starting new thread and adding it to the pool');

                            // create new thread
                            $thread = new Threadi_Thread_PHPThread(array($processorCollection, 'process'));
                            $thread->start();

                            // append it to the pool
                            $pool->add($thread);

                            $processorCollection->reset();
                        }


                        $this->message(sprintf('[--- (Add to collection) Processing %s using processor "%s" (%s) ---]', $path, $processorIdentifier, $processorName));
                        // $this->message(sprintf('Stack size: %s, Total sibling count: %s, Sibling count with same name: %s', $xmlReader->getStackSize(), $xmlReader->getSiblingCount(), $xmlReader->getSameNameSiblingCount()));
                        $processor->setPath($path);
                        $processor->setData($xmlReader);
                        $processorCollection->addProcessor($processor);



                    } catch (Exception $e) {
                        $this->logException($e, $xmlReader->getPathWithSiblingCount());
                        $this->message($processor->getSummary());
                        $this->message(Mage::helper('aoe_import/cliOutput')->getColoredString('EXCEPTION: ' . $e->getMessage(), 'red'));
                    }

                    // profiling
                    if ($this->profilerOutput) {
                        $duration = round(microtime(true) - $startTime, 2);
                        $endMemory = memory_get_usage(true);
                        $memory = round(($endMemory - $startMemory)/1024, 2); //kb
                        $endMemory = round($endMemory/(1024*1024), 2); //mb
                        file_put_contents($this->profilerOutput, "$processorName,$duration,$memory,$endMemory\n", FILE_APPEND);
                    }


                    if (!isset($this->statistics[$path])) {
                        $this->statistics[$path] = 0;
                    }
                    $this->statistics[$path]++;
                }
            }

        }
        $pool->waitTillAllReady();
        $xmlReader->close();

        // process "after" methods
        // foreach ($this->getProcessorManager()->getAllUsedProcessors() as $className => $processor) { /* @var $processor Aoe_Import_Model_Processor_Interface */
        //    $processor->after();
        // }
    }

    /**
     * Get summary
     *
     * @return string
     */
    public function getImporterSummary() {

        $summary = '';

        $summary .= "File: {$this->fileName}\n";
        $summary .= "ImportKey: {$this->importKey}\n";

        $summary .= "\n";

        $summary .= "Active processors:\n";
        foreach ($this->getProcessorManager()->getAllUsedProcessors() as $className => $processor) { /* @var $processor Aoe_Import_Model_Processor_Interface */
            $summary .= '- ' . get_class($processor);
            $options = $processor->getOptions();
            if (count($options)) {
                $summary .= ', Options: ' . var_export($options, 1);
            }
            $summary .= "\n";
        }

        $summary .= "\n";

        // collect summaries from processors:
        foreach ($this->getProcessorManager()->getAllUsedProcessors() as $className => $processor) { /* @var $processor Aoe_Import_Model_Processor_Interface */
            $processorSummary = $processor->getFinishSummary();
            if (!empty($processorSummary)) {
                $title = sprintf("Summary of processor \"%s\":\n", get_class($processor));
                $summary .= $title;
                $summary .= str_repeat('-', strlen($title)) . "\n";
                $summary .= $processorSummary . "\n\n";
            }
        }

        $summary .= parent::getImporterSummary();

        return $summary;
    }

}