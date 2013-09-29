<?php

/**
 * Abstract element processor
 *
 * @package TYPO3
 * @subpackage aoe_import
 * @author Fabrizio Branca <fabrizio.branca@aoemedia.de>
 */
abstract class Aoe_Import_Model_Processor_Abstract implements Aoe_Import_Model_Processor_Interface
{

    /**
     * @var mixed
     */
    protected $data;

    /**
     * @var array messages
     */
    protected $messages = array();

    /**
     * @var array options
     */
    protected $options = array();

    /**
     * @var array collecting some statistics for the finish summary here
     */
    protected $statistics = array();

    /**
     * @var array collecting messages for the finish summary here
     */
    protected $finishSummary = array();

    /**
     * @var bool
     */
    protected $enableLogging = false;

    /**
     * @var string
     */
    protected $logFilePath = '###LOG_DIR###/aoe_import.###NAME###.###PID###.log';

    /**
     * @var string
     */
    protected $logFormat = "[###TIME###] ###MESSAGE###\n";

    /**
     * @var int
     */
    protected $pid;

    /**
     * @var string
     */
    protected $path;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->pid = getmypid();

        $this->logFilePath = str_replace(array(
            '###LOG_DIR###',
            '###PID###',
            '###NAME###'
        ), array(
            Mage::getBaseDir('log'),
            $this->pid,
            $this->getName()
        ), $this->logFilePath);
    }

    /**
     * @param array $options
     */
    public function setOptions(array $options)
    {
        $this->options = $options;
    }

    /**
     * @return array $options
     */
    public function getOptions()
    {
        return $this->options;
    }

    /**
     * Set data
     *
     * @see setData()
     */
    public function setData($data)
    {
        $this->data = $data;
    }

    /**
     * Reset processor object to be ready for reuse
     *
     * @return void
     */
    public function reset()
    {
        $this->data = NULL;
        $this->messages = array();
    }

    /**
     * Get summary
     *
     * @return string
     */
    public function getSummary()
    {
        $message = implode("\n", $this->messages);
        if (!empty($message)) {
            $message .= "\n";
        }
        $this->messages = array(); // reset messages
        return $message;
    }

    /**
     * Get finish summary.
     * This is displayed when importing has finished or import has been aborted (CTRL+C)
     *
     * @return string
     */
    public function getFinishSummary()
    {
        $summary = '';

        if (count($this->statistics)) {
            $summary .= "\n";
            $summary .= "Processor statistics:\n";
            $summary .= "=====================\n";
            foreach ($this->statistics as $status => $value) {
                $summary .= sprintf("%s: %s\n", $status, $value);
            }
        }

        if (count($this->finishSummary)) {
            $summary .= "\n";
            $summary .= "Processor finish summary:\n";
            $summary .= "=========================\n";
            foreach ($this->finishSummary as $value) {
                $summary .= "$value\n";
            }
        }
        return $summary;
    }

    /**
     * Get processor name
     * Overwrite this if you want something else than the classname
     *
     * @return string
     */
    public function getName()
    {
        return get_class($this);
    }

    /**
     * Execute something before looping
     * (Currently only used in csv processors)
     *
     * @return void
     */
    public function before()
    {

    }

    /**
     * Execute something after looping
     * (Currently only used in csv processors)
     *
     * @return void
     */
    public function after()
    {

    }

    /**
     * Write message into log file
     *
     * @param string $message
     * @throws Exception
     * @return void
     */
    public function log($message)
    {
        if ($this->enableLogging) {
            $line = str_replace(array(
                '###PID###',
                '###TIME###',
                '###MESSAGE###'
            ), array(
                $this->pid,
                date('YmdHis'),
                $message
            ), $this->logFormat);
            if (empty($this->logFilePath)) {
                throw new Exception('No logFilePath found!');
            }
            $res = file_put_contents($this->logFilePath, $line, FILE_APPEND);
            if ($res === false) {
                throw new Exception('Error while writing log to ' . $this->logFilePath);
            }
        }
    }

    /**
     * @param string $path
     */
    public function setPath($path)
    {
        $this->path = $path;
    }

    /**
     * @return string
     */
    public function getPath()
    {
        return $this->path;
    }

}