<?php

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
     * @var bool flag that shows if CTRL+C was pressed
     */
    protected $shutDown = false;

    /**
     * @var array statistics
     */
    protected $pathCounter = array();

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
     * @var string profiler output file path
     */
    protected $profilerOutput;

    /**
     * Set profiler output file path
     *
     * @param $profilerOutput
     */
    public function setProfilerOutput($profilerOutput) {
        $this->profilerOutput = $profilerOutput;
    }

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

        // This adds the ability to decompress BZIP2 and GZIP files in-stream
        $extension = pathinfo($this->fileName, PATHINFO_EXTENSION);
        if ($extension === 'bz2') {
            $this->message('Using BZIP2 stream decompression');
            $this->fileName = 'compress.bzip2://' . $this->fileName;
        } elseif ($extension === 'gz') {
            $this->message('Using ZLIB stream decompression');
            $this->fileName = 'compress.zlib://' . $this->fileName;
        }

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
     * @param bool $verbose
     */
    public function setVerbose($verbose)
    {
        $this->verbose = $verbose;
    }

    public function getVerbose() {
        return $this->verbose;
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
        return '';
    }

    public function setEchoOutput($echoOutput) {
        $this->echoOutput = $echoOutput;
    }

    public function getOutput() {
        return $this->output;
    }

    /**
     * Increment statistic counter
     *
     * @param $type
     */
    public function incrementPathCounter($type) {
        if (!isset($this->pathCounter[$type])) {
            $this->pathCounter[$type] = 0;
        }
        $this->pathCounter[$type]++;
    }


}