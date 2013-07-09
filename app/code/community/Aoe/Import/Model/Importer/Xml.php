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
     * Import
     *
     * @throws Exception
     * @return void
     */
    protected function _import() {

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

                    $this->message(sprintf('[--- Processing %s using processor "%s" (%s) ---]', $xmlReader->getPathWithSiblingCount(), $processorIdentifier, $processorName));
                    // $this->message(sprintf('Stack size: %s, Total sibling count: %s, Sibling count with same name: %s', $xmlReader->getStackSize(), $xmlReader->getSiblingCount(), $xmlReader->getSameNameSiblingCount()));

                    try {
                        $processor->setData($xmlReader);

                        $processorReturnValue = $processor->process();

                        // storing the result to levelData
                        $xmlReader->setLevelData($processorName.'_returnValue', $processorReturnValue);

                        $this->message($processor->getSummary());

                    } catch (Exception $e) {
                        $this->logException($e, $xmlReader->getPathWithSiblingCount());
                        $this->message($processor->getSummary());
                        $this->message(Mage::helper('aoe_import/cliOutput')->getColoredString('EXCEPTION: ' . $e->getMessage(), 'red'));
                    }

                    $this->statistics[$path]++;
                }
            }

        }
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
            $summary .= "- $className";
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
                $title = sprintf("Summary of processor \"%s\":\n", $className);
                $summary .= $title;
                $summary .= str_repeat('-', strlen($title)) . "\n";
                $summary .= $processorSummary . "\n\n";
            }
        }

        $summary .= parent::getImporterSummary();

        return $summary;
    }

}