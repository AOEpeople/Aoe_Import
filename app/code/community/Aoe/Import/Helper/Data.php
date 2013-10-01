<?php

/**
 * Class Aoe_Import_Helper_Data
 *
 * @author Fabrizio Branca
 * @since 2013-06-26
 */
class Aoe_Import_Helper_Data extends Mage_Core_Helper_Abstract
{

    /**
     * Sort by priority
     *
     * @param Mage_Core_Model_Config_Element $a
     * @param Mage_Core_Model_Config_Element $b
     * @return int
     */
    public function sortByPriority(Mage_Core_Model_Config_Element $a, Mage_Core_Model_Config_Element $b)
    {
        return strcmp((string)$a->priority, (string)$b->priority) * -1;
    }

    /**
     * trim explode
     *
     * @param $delim
     * @param $string
     * @param bool $removeEmptyValues
     * @return array
     */
    public function trimExplode($delim, $string, $removeEmptyValues = false)
    {
        $explodedValues = explode($delim, $string);
        $result = array_map('trim', $explodedValues);
        if ($removeEmptyValues) {
            $temp = array();
            foreach ($result as $value) {
                if ($value !== '') {
                    $temp[] = $value;
                }
            }
            $result = $temp;
        }
        return $result;
    }

    public function filterEvents(array $regexPatterns)
    {
        $app = Mage::app();

        $reflectedClass = new ReflectionClass($app);
        $property = $reflectedClass->getProperty('_events');
        $property->setAccessible(true);
        $eventAreas = $property->getValue($app);
        foreach ($eventAreas as $area => $events) {
            foreach ($events as $eventName => $eventConfig) {
                foreach($regexPatterns as $regexPattern) {
                    if(preg_match($regexPattern, $eventName)) {
                        $events[$eventName] = false;
                        break;
                    }
                }
            }
        }
        $property->setValue($eventAreas);

        foreach(array_keys($eventAreas) as $area) {
            $eventsNode = $app->getConfig()->getNode($area)->events;
            foreach($eventsNode as $eventName => $eventNode) {
                foreach($regexPatterns as $regexPattern) {
                    if(preg_match($regexPattern, $eventName)) {
                        unset($eventsNode[$eventName]);
                        break;
                    }
                }
            }
        }
    }
}
