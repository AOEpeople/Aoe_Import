<?php

class Aoe_Import_Helper_Data extends Mage_Core_Helper_Abstract {

	/**
	 * Sort by priority
	 *
	 * @param Mage_Core_Model_Config_Element $a
	 * @param Mage_Core_Model_Config_Element $b
	 * @return int
	 */
	public function sortByPriority(Mage_Core_Model_Config_Element $a, Mage_Core_Model_Config_Element $b) {
		return strcmp((string)$a->priority, (string)$b->priority) * -1;
	}

}
