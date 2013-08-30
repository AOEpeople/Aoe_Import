<?php

if (!defined('SINGLE_QUOTE')) define('SINGLE_QUOTE', "'");
if (!defined('DOUBLE_QUOTE')) define('DOUBLE_QUOTE', '"');
if (!defined('TAB')) define('TAB', "\t");

/**
 * Abstract Importer
 */
abstract class Aoe_Import_Model_Importer_Abstract
{

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
     * @var string output buffer
     */
    protected $output;

    /**
     * @var bool
     */
    protected $echoOutput = true;

    /**
     * Do the actual import
     *
     * @return void
     */
    abstract protected function _import();

    /**
     * Import
     *
     * @throws Exception
     * @return void
     */
    public function import()
    {
        $this->startTime = microtime(true);
        if (empty($this->fileName)) {
            throw new Exception('No file selected');
        }
        if (!is_file($this->fileName)) {
            throw new Exception(sprintf('Could not find "%s"', $this->fileName));
        }

        $this->message(sprintf('Importing file "%s"', $this->fileName));

        $this->_import();

        $this->endTime = microtime(true);
    }

    /**
     * Enable cli functions
     *
     * @return void
     */
    public function enableCliFunctions()
    {
        declare(ticks = 1);
        register_shutdown_function(array($this, 'shutDown'));
        pcntl_signal(SIGTERM, array($this, 'shutDown')); // Kill
        pcntl_signal(SIGINT, array($this, 'shutDown')); // CTRL + C
    }

    /**
     * Shutdown method.
     * (This is used as a callback function)
     *
     * @return void
     */
    public function shutDown()
    {
        $this->shutDown = true;
    }

    /**
     * Include this check in your loop to detect if the importer was aborted
     *
     * @return bool
     */
    public function wasShutDown()
    {
        return $this->shutDown == true;
    }

    /**
     * Log exception
     *
     * @param Exception $e
     * @param string $key
     * @return void
     */
    protected function logException(Exception $e, $key = NULL)
    {
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
    protected function writeExceptionLog()
    {
        if (count($this->exceptions) == 0) {
            return false;
        }
        $exceptionLogPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . get_class($this) . '_' . date('Y_m_d_H_i_s') . '.log';
        $handle = fopen($exceptionLogPath, 'a');
        foreach ($this->exceptions as $path => $message) {
            fwrite($handle, $path . ': ' . $message . "\n");
        }
        fclose($handle);
        return $exceptionLogPath;
    }

    /**
     * @param bool $verbose
     */
    public function setVerbose($verbose)
    {
        $this->verbose = $verbose;
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
        if ($this->verbose) {
            $output = $message;
            if ($message && $lineBreak) {
                $output .= "\n";
            }
            $this->output .= $output;
            if ($this->echoOutput) {
                echo $output;
            }
        }
    }

    /**
     * Get summary
     *
     * @return string
     */
    public function getImporterSummary()
    {

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
        $timePerImport = $duration / $total;
        $summary .= "Duration/Process: " . number_format($timePerImport, 4) . " sec\n";
        $processesPerMinute = (1 / $timePerImport) * 60;
        $summary .= "Processes/Minute: " . intval($processesPerMinute) . "\n";
        return $summary;
    }

    public function setEchoOutput($echoOutput) {
        $this->echoOutput = $echoOutput;
    }

    public function getOutput() {
        return $this->output;
    }

}