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

    /**
     * Thread pool size
     *
     * @var int
     */
    protected $threadPoolSize = 1;

    /**
     * @var int select the highest possible number (to reduce threading overhead) that will successfully process all imports
     * before hitting the memory limit
     */
    protected $processorCollectionSize = 200;

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

            /*
            // unused leftover from TYPO3 implementation
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
            */

            $path = $xmlReader->getPath();
            // $this->message($path);


            if (in_array($xmlReader->nodeType, $nodeTypesWithProcessors)) {

                $processors = $this->getProcessorManager()->findProcessors($this->importKey, $path, $xmlReader->nodeType);

                // process the collection
                if ($processorCollection->count() >= $this->processorCollectionSize) {
                    $processorCollection->forkAndGo($pool);
                }

                $currentXmlPart = null;

                foreach ($processors as $processorIdentifier => $processor) { /* @var $processor Aoe_Import_Model_Processor_Xml_Abstract */

                    // TODO: these would only need to be set once
                    $processor->setLogFilePath(Mage::getBaseDir('log') . '/' . date('Y-m-d_H-i-s', $this->startTime) . '_' . $processorIdentifier . '.log');
                    $processor->setProfilerOutput($this->profilerOutput);

                    if (is_null($currentXmlPart)) {
                        $currentXmlPart = new SimpleXMLElement($xmlReader->readOuterXml());
                    }

                    $processorName = $processor->getName();

                    // add it to the current collection
                    $pathWithSiblingCount = $xmlReader->getPathWithSiblingCount();
                    $this->message(sprintf('[--- (Add to collection #%s) Processing %s using processor "%s" (%s) ---]',
                        ($processorCollection->getResetCounter() + 1),
                        $pathWithSiblingCount,
                        $processorIdentifier,
                        $processorName
                    ));
                    // $this->message(sprintf('Stack size: %s, Total sibling count: %s, Sibling count with same name: %s', $xmlReader->getStackSize(), $xmlReader->getSiblingCount(), $xmlReader->getSameNameSiblingCount()));

                    // cloning processor and add it to the collection
                    $processorClone = clone $processor;
                    $processorClone->setPath($pathWithSiblingCount);
                    $processorClone->setData($currentXmlPart);
                    $processorCollection->addProcessor($processorClone);

                    // capture some global statistics
                    $this->incrementPathCounter($path);
                }
            }
        }

        // process the remaining items in the collection
        $processorCollection->forkAndGo($pool);

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

        $summary .= "Importer statistics:\n";
        $summary .= "====================\n";


        $summary .= "File: {$this->fileName}\n";
        $summary .= "ImportKey: {$this->importKey}\n";

        $summary .= "\n";

        $summary .= "Active processors:\n";
        $summary .= "------------------\n";
        foreach ($this->getProcessorManager()->getAllUsedProcessors() as $className => $processor) { /* @var $processor Aoe_Import_Model_Processor_Xml_Abstract */
            $summary .= '- ' . get_class($processor);
//            $options = $processor->getOptions();
//            if (count($options)) {
//                $summary .= ', Options: ' . var_export($options, 1);
//            }
            $summary .= "\n";
            $summary .= "  Detailed log file: " . $processor->getLogFilePath() . "\n";
        }

        $summary .= "\n";

        $summary .= "Processed paths:\n";
        $summary .= "----------------\n";
        if (count($this->pathCounter)) {
            foreach ($this->pathCounter as $type => $amount) {
                $summary .= "- $type: $amount\n";
            }
        }
        $summary .= "\n";


        $summary .= "Statistics:\n";
        $summary .= "----------------\n";
        $total = array_sum($this->pathCounter);
        $summary .= "Total Processes: " . $total . "\n";
        $duration = $this->endTime - $this->startTime;
        $summary .= "Total Duration: " . number_format($duration, 2) . " sec\n";
        $timePerImport = $duration / $total;
        $summary .= "Duration/Process: " . number_format($timePerImport, 4) . " sec\n";
        $processesPerMinute = (1 / $timePerImport) * 60;
        $summary .= "Processes/Minute: " . intval($processesPerMinute) . "\n";
        $summary .= "\n";

        return $summary;
    }

}

