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
   

}
