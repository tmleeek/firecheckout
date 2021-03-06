<?php

class Billmate_Bankpay_BankpayController extends Mage_Core_Controller_Front_Action{
    /**
     * When a customer chooses Paypal on Checkout/Payment page
     *
     */

    public function redirectAction()
    {
        $session = Mage::getSingleton('checkout/session');
        $session->setPaypalStandardQuoteId($session->getQuoteId());
        $orderIncrementId = $session->getPaypalStandardQuoteId();
        $order = Mage::getModel('sales/order')->loadByIncrementId($session->getLastRealOrderId());;
		
		$status = 'pending_payment';
		$isCustomerNotified = false;
		$order->setState('new', $status, '', $isCustomerNotified);
		$order->save();

        $this->getResponse()->setBody($this->getLayout()->createBlock('billmatebankpay/bankpay_redirect')->toHtml());
        $session->unsQuoteId();
        $session->unsRedirectUrl();
    }
    /**
     * When a customer cancel payment from paypal.
     */
    public function cancelAction()
    {
        $session = Mage::getSingleton('checkout/session');
        $session->setQuoteId($session->getPaypalStandardQuoteId(true));
        if ($session->getLastRealOrderId()) {
            $order = Mage::getModel('sales/order')->loadByIncrementId($session->getLastRealOrderId());
            if ($order->getId()) {
                $order->cancel()->save();
            }
        }
        $this->_redirect('checkout/cart');
    }

    /**
     * when paypal returns
     * The order information at this point is in POST
     * variables.  However, you don't want to "process" the order until you
     * get validation from the IPN.
     */
    public function successAction()
    {
        $session = Mage::getSingleton('checkout/session');
        $orderIncrementId = $session->getPaypalStandardQuoteId();
        $order = Mage::getModel('sales/order')->loadByIncrementId($session->getLastRealOrderId());;
        
		if(empty($_POST)) $_POST = $_GET;
		
        if( !empty($_POST['status']) && $_POST['status'] != 0 ){
            
            $status = 'pending_payment';
            $comment = $this->__('Unable to complete order, Reason : ').$_POST['error_message'] ;
            $isCustomerNotified = true;
            $order->setState('new', $status, $comment, $isCustomerNotified);
            $order->save();
            $order->sendOrderUpdateEmail(true, $comment);
            
            Mage::getSingleton('core/session')->addError($this->__('Unable to process with payment gateway :').$_POST['error_message']);

            $this->_redirect(Mage::getStoreConfig('payment/billmatebankpay/bank_error_page'));
        }else{
            
            $gateway =  Mage::getSingleton('billmatebankpay/gateway');
            $gateway->makePayment($order);
            
			$status = Mage::getStoreConfig('payment/billmatebankpay/order_status');
			
			$isCustomerNotified = false;
			$order->setState('new', $status, '', $isCustomerNotified);
			$order->save();

            $session->setQuoteId($session->getPaypalStandardQuoteId(true));
            Mage::getSingleton('checkout/session')->getQuote()->setIsActive(false)->save();
			$order->sendNewOrderEmail(); 
            $this->_redirect('checkout/onepage/success', array('_secure'=>true));
        }
    }
}