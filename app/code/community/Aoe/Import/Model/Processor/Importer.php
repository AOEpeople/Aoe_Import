<?php

/**
 * Class Tx_AoeImport_Domain_Model_XmlReader_Importer
 *
 * @author Fabrizio Branca
 * @since 2013-06-26
 */
class Aoe_Import_Model_Importer {

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
     * Constructor
     *
     * @param Aoe_Import_Model_ProcessorManager $processorManager
     * @param string $xmlFile
     */
    public function __construct(Aoe_Import_Model_ProcessorManager $processorManager, $xmlFile) {
        $this->processorManager = $processorManager;
        $this->fileName = $xmlFile;
    }

    /**
     * @param string $skippingUntil
     */
    public function setSkippingUntil($skippingUntil) {
        $this->skippingUntil = $skippingUntil;
    }

    /**
     * @param string $importKey
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

        $xmlReader = Mage::getSingleton('aoe_import/xmlReaderWrapper'); /* @var $xmlReader Aoe_Import_Model_XmlReaderWrapper */

        $this->message('Loading file... ', false);
        $processorReturnValue = $xmlReader->open($this->fileName);
        if ($processorReturnValue === false) { throw new Exception('Error while opening file in XMLReader'); }
        $this->message('done', true);

        $this->message('Find processors... ', false);
        if ($this->processorManager->hasProcessorsForImportKey($this->importKey) === false) {
            throw new Exception(sprintf('No processors found for importKey "%s"', $this->importKey));
        }
        $nodeTypesWithProcessors = $this->processorManager->getRegisteredNodeTypes();
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

	        // this is a huge memory consumer on initial iteration
            // if (trim($xmlReader->readInnerXml()) == '') { continue; }

            $path = $xmlReader->getPath();

            if (in_array($xmlReader->nodeType, $nodeTypesWithProcessors)) {

                $processors = $this->processorManager->findProcessors($path, $this->importKey, $xmlReader->nodeType);

                foreach ($processors as $processor) { /* @var $processor Tx_AoeImport_Domain_Model_XmlReader_ElementProcessorInterface */

                    $processorName = $processor->getName();

                    $this->message(sprintf('[--- Processing %s using processor %s ---]', $xmlReader->getPathWithSiblingCount(), $processorName));
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
                        $this->message(Tx_AoeTools_CliOutput::getColoredString('EXCEPTION: ' . $e->getMessage(), 'red'));
                    }

                    $this->statistics[$path]++;
                }
            }

        }
        $xmlReader->close();

        // process "after" methods
        foreach ($this->processorManager->getAllUsedProcessors() as $className => $processor) { /* @var $processor Tx_AoeImport_Domain_Model_ProcessorInterface */
            $processor->after();
        }
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
        foreach ($this->processorManager->getAllUsedProcessors() as $className => $processor) { /* @var $processor Tx_AoeImport_Domain_Model_XmlReader_ElementProcessorInterface */
            $summary .= "- $className";
            $options = $processor->getOptions();
            if (count($options)) {
                $summary .= ', Options: ' . var_export($options, 1);
            }
            $summary .= "\n";
        }

        $summary .= "\n";

        // collect summaries from processors:
        foreach ($this->processorManager->getAllUsedProcessors() as $className => $processor) { /* @var $processor Tx_AoeImport_Domain_Model_XmlReader_ElementProcessorInterface */
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

	/**
	 * @var string path to file
	 */
	protected $fileName;

	/**
	 * @var bool
	 */
	protected $verbose = true;

	/**
	 * @var array exception messages
	 */
	protected $exceptions = array();

	/**
	 * @var bool flag that shows if CTRL+C was pressed
	 */
	protected $shutDown = false;

	/**
	 * @var array statistics
	 */
	protected $statistics = array();

	/**
	 * @var float start time
	 */
	protected $startTime;

	/**
	 * @var float end time
	 */
	protected $endTime;

	/**
	 * Import
	 *
	 * @throws Exception
	 * @return void
	 */
	public function import() {
		$this->startTime = microtime(true);
		if (empty($this->fileName)) { throw new Exception('No file selected'); }
		if (!is_file($this->fileName)) { throw new Exception(sprintf('Could not find "%s"', $this->fileName)); }

		$this->message(sprintf('Importing file "%s"', $this->fileName));

		$this->_import();

		$this->endTime = microtime(true);
	}

	/**
	 * Enable cli functions
	 *
	 * @return void
	 */
	public function enableCliFunctions() {
		declare(ticks = 1);
		register_shutdown_function(array($this, 'shutDown'));
		pcntl_signal(SIGTERM, array($this, 'shutDown')); // Kill
		pcntl_signal(SIGINT, array($this, 'shutDown'));  // CTRL + C
	}

	/**
	 * Shutdown method.
	 * (This is used as a callback function)
	 *
	 * @return void
	 */
	public function shutDown() {
		$this->shutDown = true;
	}

	/**
	 * Include this check in your loop to detect if the importer was aborted
	 *
	 * @return bool
	 */
	public function wasShutDown() {
		return $this->shutDown == true;
	}

	/**
	 * Log exception
	 *
	 * @param Exception $e
	 * @param string $key
	 * @return void
	 */
	protected function logException(Exception $e, $key=NULL) {
		if (!is_null($key)) {
			$this->exceptions[$key] = $e->getMessage() . "\n" . $e->getTraceAsString() . "\n\n";
		} else {
			$this->exceptions[] = $e->getMessage() . "\n" . $e->getTraceAsString() . "\n\n";
		}
	}

	/**
	 * Write exceptions to  logfile
	 *
	 * @return string filename
	 */
	protected function writeExceptionLog() {
		if (count($this->exceptions) == 0) {
			return false;
		}
		$exceptionLogPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . get_class($this). '_' . date('Y_m_d_H_i_s') . '.log';
		$handle = fopen($exceptionLogPath, 'a');
		foreach ($this->exceptions as $path => $message) {
			fwrite($handle, $path . ': ' . $message . "\n");
		}
		fclose($handle);
		t3lib_div::fixPermissions($exceptionLogPath);
		return $exceptionLogPath;
	}

	/**
	 * @param bool $verbose
	 */
	public function setVerbose($verbose) {
		$this->verbose = $verbose;
	}

	/**
	 * Print message
	 *
	 * @param string $message
	 * @param bool $lineBreak
	 * @return void
	 */
	protected function message($message, $lineBreak = true) {
		if ($this->verbose) {
			echo $message;
			if ($message && $lineBreak) {
				echo "\n";
			}
		}
	}

	/**
	 * Get summary
	 *
	 * @return string
	 */
	public function getImporterSummary() {

		$summary = '';

		$exceptionLogPath = $this->writeExceptionLog();

		$summary .= "Importer statistics:\n";
		$summary .= "====================\n";

		if (count($this->statistics)) {
			foreach ($this->statistics as $type => $amount) {
				$summary .= "  $type: $amount\n";
			}
		}

		if ($exceptionLogPath) {
			$summary .= "\n[!!!] EXCEPTIONS: " . count($this->exceptions) . " (see $exceptionLogPath)\n";
		} else {
			$summary .= "\nNo exceptions were thrown.\n";
		}

		$summary .= "\n";

		$total = array_sum($this->statistics);
		$summary .= "Total Processes: " . $total . "\n";
		$duration = $this->endTime - $this->startTime;
		$summary .= "Total Duration: " . number_format($duration, 2) . " sec\n";
		$timePerImport = $duration/$total;
		$summary .= "Duration/Process: " . number_format($timePerImport, 4) . " sec\n";
		$processesPerMinute = (1/$timePerImport) * 60;
		$summary .= "Processes/Minute: " . intval($processesPerMinute) . "\n";
		return $summary;
	}


}
