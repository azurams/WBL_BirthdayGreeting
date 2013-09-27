<?php
class WBL_BirthdayGreeting_Model_Cron
{
    public function sendBirthayEmail()
    {
        //this collection get all users which have birthday on today
        $customer = Mage::getModel("customer/customer")->getCollection();
        $customer->addFieldToFilter('dob', array('like' => '%'.date("m").'-'.date("d").' 00:00:00'));
        $customer->addNameToSelect();
        $items = $customer->getItems();
        foreach($items as $item)
        {
        	Mage::log($item->getData());
        }
        return $this;
    }
}
