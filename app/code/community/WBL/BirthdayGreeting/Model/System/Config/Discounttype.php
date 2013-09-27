<?php

class WBL_BirthdayGreeting_Model_System_Config_Discounttype
{
    /**
     * @return array
     */
    public function toOptionArray()
    {
        $options = array(
            array('value'=> 1, 'label' => Mage::helper('wbl_birthdaygreeting')->__('Fixed amount')),
            array('value'=> 2, 'label' => Mage::helper('wbl_birthdaygreeting')->__('Percentage'))
        );
        return $options;
    }
}