<?php
class WBL_BirthdayGreeting_Model_Cron
{
    
    /**
     *
     */
    public function process()
    {
        $allStores = Mage::app()->getStores();
        foreach($allStores as $storeId => $val)
        {
            if(Mage::getStoreConfig(WBL_BirthdayGreeting_Model_System_Config::ACTIVE,$storeId)) {
                $this->_processBirthday($storeId);
            }
        }
    }

    protected function _processBirthday($storeId)
    {
        Mage::app()->setCurrentStore($storeId);

        $days           = Mage::getStoreConfig(WBL_BirthdayGreeting_Model_System_Config::DAYS,$storeId);
        $customerGroups = explode(",",Mage::getStoreConfig(WBL_BirthdayGreeting_Model_System_Config::CUSTOMER_GROUPS, $storeId));
        $senderId       = Mage::getStoreConfig(WBL_BirthdayGreeting_Model_System_Config::SENDER,$storeId);
        $sender         = array('name'=>Mage::getStoreConfig("trans_email/ident_$senderId/name",$storeId), 'email'=> Mage::getStoreConfig("trans_email/ident_$senderId/email",$storeId));
        $templateId     = Mage::getStoreConfig(WBL_BirthdayGreeting_Model_System_Config::TEMPLATE,$storeId);
        $mailSubject    = Mage::getStoreConfig(WBL_BirthdayGreeting_Model_System_Config::SUBJECT,$storeId);
        $sendCoupon     = Mage::getStoreConfig(WBL_BirthdayGreeting_Model_System_Config::COUPON,$storeId);
        $customerGroupsCoupon = explode(",",Mage::getStoreConfig(WBL_BirthdayGreeting_Model_System_Config::CUSTOMER_COUPON, $storeId));


        $adapter        = Mage::getSingleton('core/resource')->getConnection('sales_read');
        $collection     = Mage::getModel('customer/customer')->getCollection();
        
		//$customer
        $date = date("Y-m-d H:i:s");
        $date2 = date("Y-m-d H:i:s",strtotime(" - $days days"));
        $month = date("m",strtotime($date2));
        $day = date("d",strtotime($date2));

        $collection->addAttributeToFilter('dob',array('neq'=>'null'))
                    ->addFieldToFilter('store_id',array('eq'=>$storeId));
        if(count($customerGroups)) {
            $collection->addFieldToFilter('group_id',array('in'=>$customerGroups));
        }
        $collection->addFieldToFilter('dob', array('like' => '%'.$month.'-'.$day.' 00:00:00'));

        foreach($collection as $customer) {
            $translate = Mage::getSingleton('core/translate');
            $cust = Mage::getModel('customer/customer')->load($customer->getEntityId());
            if (Mage::getStoreConfig(WBL_BirthdayGreeting_Model_System_Config::EMAIL_ADDRESS_DEBUG,$storeId)) {
				$email = Mage::getStoreConfig(WBL_BirthdayGreeting_Model_System_Config::EMAIL_ADDRESS_DEBUG,$storeId);
            } else {
	            $email = $cust->getEmail();
            }

            $name = $cust->getFirstname().' '.$cust->getLastname();
            $vars = array();
            $couponcode = '';
            if($sendCoupon && in_array($customer->getGroupId(),$customerGroupsCoupon)) {
                if(Mage::getStoreConfig(WBL_BirthdayGreeting_Model_System_Config::AUTOMATIC,$storeId)==WBL_BirthdayGreeting_Model_System_Config::COUPON_AUTOMATIC) {
                    list($couponcode,$discount,$toDate) = $this->_createNewCoupon($storeId,$email);
                    $vars = array('couponcode'=>$couponcode,'discount' => $discount, 'todate' => $toDate, 'name' => $name);
                }
                else {
                    $couponcode = Mage::getStoreConfig(WBL_BirthdayGreeting_Model_System_Config::COUPON_CODE);
                    $vars = array('couponcode'=>$couponcode, 'name' => $name);
                }

            }

        	$mail = Mage::getModel('core/email_template')->setTemplateSubject($mailSubject)->sendTransactional($templateId,$sender,$email,$name,$vars,$storeId);
        	
            if (!$mail->getSentSuccess()) {
                Mage::log('Error: Birthday Greeting Mail was not sent to '. $name . ' - ' . $email);
            } else {
                Mage::log('Birthday Greeting Mail was sent to ' . $name . ' - ' . $email);
            } 

        	$translate->setTranslateInLine(true);
        }
    }


    protected function _createNewCoupon($store,$email)
    {
        $couponamount = Mage::getStoreConfig(WBL_BirthdayGreeting_Model_System_Config::DISCOUNT, $store);
        $couponexpiredays = Mage::getStoreConfig(WBL_BirthdayGreeting_Model_System_Config::EXPIRE, $store);
        $coupontype = Mage::getStoreConfig(WBL_BirthdayGreeting_Model_System_Config::DISCOUNT_TYPE, $store);
        $couponlength = Mage::getStoreConfig(WBL_BirthdayGreeting_Model_System_Config::LENGTH, $store);
        $couponlabel = Mage::getStoreConfig(WBL_BirthdayGreeting_Model_System_Config::COUPON_LABEL, $store);
        $websiteid =  Mage::getModel('core/store')->load($store)->getWebsiteId();

        $fromDate = date("Y-m-d");
        $toDate = date('Y-m-d', strtotime($fromDate. " + $couponexpiredays day"));
        if($coupontype == 1) {
            $action = 'cart_fixed';
            $discount = Mage::app()->getStore($store)->getCurrentCurrencyCode()."$couponamount";
        }
        elseif($coupontype == 2) {
            $action = 'by_percent';
            $discount = "$couponamount%";
        }
        $customer_group = new Mage_Customer_Model_Group();
        $allGroups  = $customer_group->getCollection()->toOptionHash();
        $groups = array();
        foreach($allGroups as $groupid=>$name) {
            $groups[] = $groupid;
        }
        $coupon_rule = Mage::getModel('salesrule/rule');
        $coupon_rule->setName("Birthday coupon $email")
            ->setDescription("Birthday coupon $email")
            ->setFromDate($fromDate)
            ->setToDate($toDate)
            ->setIsActive(1)
            ->setCouponType(2)
            ->setUsesPerCoupon(1)
            ->setUsesPerCustomer(1)
            ->setCustomerGroupIds($groups)
            ->setProductIds('')
            ->setLengthMin($couponlength)
            ->setLengthMax($couponlength)
            ->setSortOrder(0)
            ->setStoreLabels(array($couponlabel))
            ->setSimpleAction($action)
            ->setDiscountAmount($couponamount)
            ->setDiscountQty(0)
            ->setDiscountStep('0')
            ->setSimpleFreeShipping('0')
            ->setApplyToShipping('0')
            ->setIsRss(0)
            ->setWebsiteIds($websiteid);
        $uniqueId = Mage::getSingleton('salesrule/coupon_codegenerator', array('length' => $couponlength))->generateCode();
        $coupon_rule->setCouponCode($uniqueId);
        $coupon_rule->save();
        return array($uniqueId,$discount,$toDate);
    }
}