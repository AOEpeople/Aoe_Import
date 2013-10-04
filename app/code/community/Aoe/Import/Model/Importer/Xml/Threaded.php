<?php
require_once 'Threadi/Loader.php';

class Aoe_Import_Model_Importer_Xml_Threaded extends Aoe_Import_Model_Importer_Xml
{
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
    protected $threadBatchSize = 200;

    /**
     * @var Threadi_Pool
     */
    protected $threadPool;

    protected $elementsData = array();

    protected $threadCounter = 0;

    /**
     * @param int $threadPoolSize
     */
    public function setThreadPoolSize($size)
    {
        $this->threadPoolSize = max(intval($size), 1);
    }

    /**
     * @param int $threadBatchSize
     */
    public function setThreadBatchSize($size)
    {
        $this->threadBatchSize = max(intval($size), 1);
    }


    protected function _import()
    {
        $this->message('Initializing thread pool...');
        $this->threadPool = new Threadi_Pool($this->threadPoolSize);

        parent::_import();
    }

    protected function processElement(SimpleXMLElement $element, $importKey, $nodeType, $path, $countedPath, $correlationIdentifier)
    {
        if (count($this->elementsData) >= $this->threadBatchSize) {
            $this->runBatch();
        }

        $this->elementsData[] = array(
            'element'      => $element,
            'import_key'   => $importKey,
            'node_type'    => $nodeType,
            'path'         => $path,
            'counted_path' => $countedPath,
        );
    }

    protected function finishProcessing()
    {
        $this->runBatch();

        $this->threadPool->waitTillAllReady();

        // Close existing DB connection to ensure we have a valid db connection resource
        Mage::getSingleton('core/resource')->getConnection('core_write')->closeConnection();
    }

    protected function runBatch()
    {
        // Wait until there is a free slot in the pool
        $this->threadPool->waitTillReady();

        // create new thread
        $this->threadCounter++;
        $this->message("Starting thread #{$this->threadCounter} and adding it to the pool with " . count($this->elementsData) . " records");
        $thread = new Threadi_Thread_PHPThread(array($this, 'processBatch'));
        $thread->start();

        // append it to the pool
        $this->threadPool->add($thread);

        // Reset
        $this->elementsData = array();
    }

    public function processBatch()
    {
        // Close existing DB connection to ensure we have a valid db connection resource
        Mage::getSingleton('core/resource')->getConnection('core_write')->closeConnection();

        $count = count($this->elementsData);
        foreach ($this->elementsData as $i => $elementData) {
            parent::processElement(
                $elementData['element'],
                $elementData['import_key'],
                $elementData['node_type'],
                $elementData['path'],
                $elementData['counted_path'],
                sprintf('%s: %s/%s', $this->threadCounter, ($i + 1), $count)
            );
        }
    }

    /**
     * Get summary
     *
     * @return string
     */
    public function getImporterSummary()
    {
        $summary = parent::getImporterSummary();

        $summary .= "Thread Info:\n";
        $summary .= "----------------\n";
        $summary .= "Total Threads: " . $this->threadCounter . "\n";
        $summary .= "\n";

        return $summary;
    }
}
