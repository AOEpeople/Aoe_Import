<?php

/**
 * Class Aoe_Import_Model_ProcessorManager
 *
 * @author Fabrizio Branca
 * @since  2013-06-26
 */
class Aoe_Import_Model_ProcessorManager
{

    /**
     * @var array
     */
    protected $processorConfigurations = array();

    /**
     * @var array
     */
    protected $matchesCache = array();

    /**
     * @var string
     */
    protected $logFilePathTemplate;

    public function getLogFilePathTemplate()
    {
        return $this->logFilePathTemplate;
    }

    public function setLogFilePathTemplate($template)
    {
        $this->logFilePathTemplate = $template;
        return $this;
    }

    /**
     * Find processors by import key, path and nodeTypes.
     * Returns an array of processorIdentifiers sorted by priority
     *
     * @param $importKey
     * @param $path
     * @param $nodeType
     *
     * @return array array(<processorIdentifier> => <Aoe_Import_Model_Processor_Interface>)
     */
    public function findProcessors($importKey, $path, $nodeType)
    {

        if (!isset($this->matchesCache[$importKey][$nodeType][$path])) {
            $this->matchesCache[$importKey][$nodeType][$path] = array();

            $tmp = array();
            foreach ($this->processorConfigurations[$importKey][$nodeType] as $pathFilter => $processorConfigurations) {
                if (preg_match($pathFilter, $path) > 0) {
                    $tmp = array_merge($tmp, $processorConfigurations);
                }
            }
            // reverse sorting by value (priority) while maintaing the keys (processorIdentifier)
            $helper = Mage::helper('aoe_import');
            /* @var $helper Aoe_Import_Helper_Data */
            uasort($tmp, array($helper, 'sortByPriority'));
            foreach ($tmp as $processorIdentifier => $conf) {
                /* @var $conf Mage_Core_Model_Config_Element */
                $this->matchesCache[$importKey][$nodeType][$path][$processorIdentifier] = $this->getProcessor($conf, $processorIdentifier);
            }
        }

        return $this->matchesCache[$importKey][$nodeType][$path];
    }

    /**
     * Load configurations from config
     */
    public function loadFromConfig()
    {
        // reset
        $this->processorConfigurations = array();
        $this->matchesCache = array();

        $conf = Mage::getConfig()->getNode('aoe_import');
        foreach ($conf->children() as $importKey => $importKeyConf) {
            /* @var $importKeyConf Mage_Core_Model_Config_Element */
            foreach ($importKeyConf->children() as $processorIdentifier => $processorConf) {
                /* @var $processorConf Mage_Core_Model_Config_Element */
                $pathFilter = (string)$processorConf->pathFilter;
                $nodeType = (string)$processorConf->nodeType;
                if (!empty($nodeType)) {
                    $tmp = constant($nodeType);
                    if (!is_null($tmp)) {
                        $nodeType = $tmp;
                    }
                }
                if (empty($nodeType)) {
                    $nodeType = XMLReader::ELEMENT;
                }
                $this->registerProcessorConfiguration($processorIdentifier, $importKey, $pathFilter, $nodeType, $processorConf);
            }
        }
    }

    /**
     * Get all processors that have been used in this process
     *
     * @param bool $flat
     *
     * @return array
     */
    public function getAllUsedProcessors($flat = true)
    {
        if (!$flat) {
            return $this->matchesCache;
        } else {
            $result = array();
            foreach ($this->matchesCache as $importKey => $nodeTypes) {
                foreach ($nodeTypes as $nodeType => $paths) {
                    foreach ($paths as $path => $processors) {
                        foreach ($processors as $processorIdentifier => $processor) {
                            $result[] = $processor;
                        }
                    }
                }
            }
            return $result;
        }
    }


    /**
     * Get a list of a list nodeTypes a processor is defined for
     *
     * @return array
     */
    public function getRegisteredNodeTypes()
    {
        $nodeTypes = array();
        foreach ($this->processorConfigurations as $importKey => $conf) {
            $nodeTypes = array_merge($nodeTypes, array_keys($conf));
        }
        return $nodeTypes;
    }

    /**
     * Check if there are processors for a given import key
     *
     * @param $importKey
     *
     * @return bool
     */
    public function hasProcessorsForImportKey($importKey)
    {
        return count($this->processorConfigurations[$importKey]) > 0;
    }

    /**
     * Get processor object
     *
     * @param Mage_Core_Model_Config_Element $conf
     *
     * @return Aoe_Import_Model_Processor_Interface
     * @throws Exception
     */
    protected function getProcessor(Mage_Core_Model_Config_Element $conf,$processorIdentifier)
    {
        $processorClassName = (string)$conf->class;
        $processor = Mage::getModel($processorClassName);
        /* @var $processor Aoe_Import_Model_Processor_Xml_Abstract */

        // check if it implements the correct interface
        if (!is_object($processor)) {
            Mage::throwException(sprintf('Class "%s" is not an object"', $processorClassName));
        } elseif (!$processor instanceof Aoe_Import_Model_Processor_Interface) {
            Mage::throwException(sprintf('Class "%s" (%s) does not implement interface "Aoe_Import_Model_Processor_Interface"', get_class($processor), $processorClassName));
        }

        // pass options, if set
        if(isset($conf->options)) {
            $processor->setOptions($conf->options->asArray());
        }

        if ($this->logFilePathTemplate) {
            $logFilePath = str_replace('###IDENTIFIER###', $processorIdentifier, $this->logFilePathTemplate);
            $processor->setLogFilePath($logFilePath);
        }

        return $processor;
    }

    /**
     * Get processor configurations
     *
     * @return array
     */
    public function getProcessorConfigurations()
    {
        return $this->processorConfigurations;
    }

    /**
     * Register processor configuration
     *
     * @param                                $processorIdentifier
     * @param                                $importKey
     * @param                                $pathFilter
     * @param                                $nodeType
     * @param Mage_Core_Model_Config_Element $configuration
     */
    public function registerProcessorConfiguration($processorIdentifier, $importKey, $pathFilter, $nodeType, Mage_Core_Model_Config_Element $configuration)
    {
        $this->processorConfigurations[$importKey][$nodeType][$pathFilter][$processorIdentifier] = $configuration;
    }
}
