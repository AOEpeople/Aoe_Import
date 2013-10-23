<?php
class Aoe_Import_Model_Importer_Xml_SkipElementException extends Mage_Core_Exception
{
    public function __construct($message = "", $code = 0, Exception $previous = null)
    {
        if ((empty($message))) {
            $message = 'Skipped';
        }

        parent::__construct($message, $code, $previous);
    }
}
