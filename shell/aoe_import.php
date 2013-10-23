<?php

require_once 'abstract.php';

/**
 * Class Aoe_Import_Shell_Import
 *
 * @author Fabrizio Branca
 * @since 2013-06-26
 */
class Aoe_Import_Shell_Import extends Mage_Shell_Abstract
{

    /**
     * Run script
     *
     * @return void
     */
    public function run()
    {
        $action = $this->getArg('action');
        if (empty($action)) {
            echo $this->usageHelp();
        } else {
            $actionMethodName = $action . 'Action';
            if (method_exists($this, $actionMethodName)) {
                $this->$actionMethodName();
            } else {
                echo "Action $action not found!\n";
                echo $this->usageHelp();
                exit(1);
            }
        }
    }


    /**
     * Retrieve Usage Help Message
     *
     * @return string
     */
    public function usageHelp()
    {
        $help = 'Available actions: ' . "\n";
        $methods = get_class_methods($this);
        foreach ($methods as $method) {
            if (substr($method, -6) == 'Action') {
                $help .= '    -action ' . substr($method, 0, -6);
                $helpMethod = $method . 'Help';
                if (method_exists($this, $helpMethod)) {
                    $help .= $this->$helpMethod();
                }
                $help .= "\n";
            }
        }
        return $help;
    }

    /**
     * Run a job now
     *
     * @return void
     */
    public function importXmlAction()
    {
        $files = $this->getInputFiles($this->getArg('files'));

        if (count($files) == 0) {
            Mage::throwException('No input files found');
        }
        $importKey = $this->getArg('importKey');
        if (empty($importKey)) {
            Mage::throwException('No import key given.');
        }

        $importer = Mage::getModel('aoe_import/importer_xml'); /* @var $importer Aoe_Import_Model_Importer_Xml */


        $profilerPath = $this->getArg('profilerPath');
        if (!empty($profilerPath)) {
            $importer->setProfilerOutput($profilerPath);
        }

        $threadPoolSize = $this->getArg('threadPoolSize');
        if (!empty($threadPoolSize)) {
            $importer->setThreadPoolSize($threadPoolSize);
        }

        $processorCollectionSize = $this->getArg('processorCollectionSize');
        if (!empty($processorCollectionSize)) {
            $importer->setProcessorCollectionSize($processorCollectionSize);
        }

        foreach ($files as $file) {
            $importer->setFileName($file);
            $importer->setImportKey($importKey);
            $importer->import();
        }

    }

    /**
     * Display extra help
     *
     * @return string
     */
    public function importXmlActionHelp()
    {
        return " -files <files> -importKey <importKey> -profilerPath <fileName> -threadPoolSize <int> -processorCollectionSize <int>";
    }

    /**
     * Get input files
     *
     * @param $files
     * @return array
     */
    protected function getInputFiles($files)
    {
        $inputFiles = array();
        $xmlFile = $files;
        $files = Mage::helper('aoe_import')->trimExplode(PATH_SEPARATOR, $xmlFile);

        foreach ($files as $filePattern) {
            $tmp = glob($filePattern);
            $inputFiles = array_merge($inputFiles, $tmp);
        }

        return $inputFiles;
    }

    /**
     * Show configuration
     */
    public function showConfigurationAction() {
        $helper = Mage::helper('aoe_import');
        $processorManager = Mage::getModel('aoe_import/processorManager'); /* @var $processorManager Aoe_Import_Model_ProcessorManager */
        $processorManager->loadFromConfig();
        foreach ($processorManager->getProcessorConfigurations() as $importKey => $a) {
            echo "ImportKey: '$importKey':\n";
            foreach ($a as $nodeType => $b) {
                foreach ($b as $pathFilter => $c) {
                    echo "  Path: '$pathFilter' (".$helper->xmlReaderConstantToText($nodeType)."):\n";
                    foreach ($c as $processorIdentifier => $d) {
                        echo "    Processor: '$processorIdentifier':\n";
                    }
                }
            }
            echo "\n";
        }
    }



}

//$_GET['profile'] = true;
//require_once   '../app/Mage.php';
//Varien_Profiler::start("wrapper");

$shell = new Aoe_Import_Shell_Import();
$shell->run();

//Varien_Profiler::stop("wrapper");
//Mage::helper('aoe_profiler')->renderProfilerOutputToFile();