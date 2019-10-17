<?php
/**
*  @category   Inovarti
*  @package    Inovarti_Pagarme
*  @copyright  Copyright (C) 2016 Pagar Me (http://www.pagar.me/)
*  @author     Lucas Santos <lucas.santos@pagar.me>
*/

class Inovarti_Pagarme_Model_Cc extends Inovarti_Pagarme_Model_Abstract
{
    protected $_code                        = 'pagarme_cc';
    protected $_formBlockType               = 'pagarme/form_cc';
    protected $_infoBlockType               = 'pagarme/info_cc';
    protected $_isGateway                   = true;
    protected $_canAuthorize                = true;
    protected $_canCapture                  = true;
    protected $_canRefund                   = true;
    protected $_canUseForMultishipping      = true;
    protected $_canManageRecurringProfiles  = false;

    public function assignData($data)
    {
        if (!($data instanceof Varien_Object)) {
            $data = new Varien_Object($data);
        }

        $info = $this->getInfoInstance();

        $info->setInstallments($data->getInstallments())
            ->setInstallmentDescription($data->getInstallmentDescription())
            ->setPagarmeCardHash($data->getPagarmeCardHash());

        return $this;
    }

    public function authorize(Varien_Object $payment, $amount)
    {
        $this->_place($payment, $this->getGrandTotalFromPayment($payment), self::REQUEST_TYPE_AUTH_ONLY);
        return $this;
    }

    public function capture(Varien_Object $payment, $amount)
    {
        $amount = $this->getGrandTotalFromPayment($payment);

        if ($payment->getPagarmeTransactionId()) {
            $this->_place($payment, $amount, self::REQUEST_TYPE_CAPTURE_ONLY);
            return $this;
        }

        $this->_place($payment, $amount, self::REQUEST_TYPE_AUTH_CAPTURE);
        return $this;
    }

    public function calculateInterestFeeAmount($amount, $numberOfInstallments, $installmentConfig)
    {
        $availableInstallments = $this->getAvailableInstallments($amount, $installmentConfig);

        if(!$availableInstallments)
            return null;

        $installment = array_shift(array_filter($availableInstallments,
            function ($availableInstallment) use ($numberOfInstallments) {
                return $availableInstallment->getInstallment() == $numberOfInstallments;
            }
        ));

        if($installment != null)
            return Mage::helper('pagarme')->convertCurrencyFromCentsToReal(($installment->getAmount() - $amount));
        return 0;
    }

    private function getAvailableInstallments($amount, $installmentConfig)
    {
        $data = new Varien_Object();
        $data->setMaxInstallments($installmentConfig->getMaxInstallments());
        $data->setFreeInstallments($installmentConfig->getFreeInstallments());
        $data->setInterestRate($installmentConfig->getInterestRate());
        $data->setAmount($amount);

        $api = Mage::getModel('pagarme/api');
        return $api->calculateInstallmentsAmount($data)
            ->getInstallments();
    }

    /**
     * @param $payment
     * @param $amount
     * @param $requestType
     * @param $customer
     * @param $checkout
     * @return Varien_Object
     */
    protected function prepareRequestParams($payment, $amount, $requestType, $customer, $checkout)
    {
        $order = $payment->getOrder();
        $helperPagarme = Mage::helper('pagarme');
        $splitRules = $this->prepareSplit($payment->getOrder()->getQuote());

        $customer = $payment->getOrder()->getCustomer();
        $customerBillingAddress = $payment->getOrder()->getBillingAddress();

        $requestParams = new Varien_Object();

        $phoneNumbers = array();
        $phoneNumbers[] = "+55".str_replace(array("(",")"," ","-"), "", $customerBillingAddress->getTelephone());
        if($customerBillingAddress->getFax())
        {
            $phoneNumbers[] = "+55".str_replace(array("(",")"," ","-"), "", $customerBillingAddress->getFax());
        }
        
        $billingData = new Varien_Object();
        $addressData = new Varien_Object();
        $addressData->setCountry(strtolower($customerBillingAddress->getCountry()));
        $addressData->setState($customerBillingAddress->getRegionCode());
        $addressData->setCity($customerBillingAddress->getCity());
        $addressData->setStreet($customerBillingAddress->getStreet1());
        $addressData->setStreetNumber($customerBillingAddress->getStreet2());
        if($customerBillingAddress->getStreet3())
        {
            $addressData->setComplementary($customerBillingAddress->getStreet3());
        }
        $addressData->setNeighborhood($customerBillingAddress->getStreet4());
        $addressData->setZipcode(Zend_Filter::filterStatic($customerBillingAddress->getPostcode(), 'Digits'));
        $billingData->setName($customerBillingAddress->getName());
        $billingData->setAddress($addressData);

        $shippingData = new Varien_Object();
        $shippingData->setName($customerBillingAddress->getName());
        
        if($order->getShippingAmount() > 0)
        {
            $shippingData->setFee(number_format($order->getShippingAmount(), 2, '', ''));
        }
        else
        {
            $shippingData->setFee(0);
        }

        $shippingData->setAddress($addressData);

        $customerData = new Varien_Object();
        $customerData->setExternalId($customer->getId());
        $customerData->setName($customer->getName());
        $customerData->setEmail($customer->getEmail());
        $customerData->setType("individual");
        $customerData->setCountry(strtolower($customerBillingAddress->getCountry()));
        $customerData->setBirthday(date("Y-m-d", strtotime($customer->getDob())));
        $customerData->setPhoneNumbers($phoneNumbers);

        $documentData = new Varien_Object();
        $documentData->setType("cpf");
        $documentData->setNumber(preg_replace( '/[^0-9]/', '', $customer->getTaxvat()));

        $customerData->setDocuments(array($documentData));

        $itemsData = new Varien_Object();
        $data = array();
        foreach($order->getAllVisibleItems() as $item)
        {
            $data[] = array(    "id"            =>  $item->getSku(),
                                "title"         =>  $item->getName(),
                                "unit_price"    =>  number_format($item->getPrice(), 2, '', ''),
                                "quantity"      =>  $item->getQtyOrdered(),
                                "tangible"      =>  "true");
        }

        $itemsData->setData($data);

        $requestParams->setAmount(Mage::helper('pagarme')->formatAmount($amount))
                ->setCustomer($customerData)
                ->setBilling($billingData)
                ->setShipping($shippingData)
                ->setItems($itemsData);

        if($requestType == self::REQUEST_TYPE_AUTH_CAPTURE)
        {
            $requestParams->setCapture("true");
        }
        else
        {
            $requestParams->setCapture("false");    
        }
        

        if ($splitRules) {
            $requestParams->setSplitRules($splitRules);
        }

        if ($checkout) {
            $requestParams->setPaymentMethod($payment->getPagarmeCheckoutPaymentMethod());
            $requestParams->setCardHash($payment->getPagarmeCheckoutHash());
            $requestParams->setInstallments($payment->getPagarmeCheckoutInstallments());
        } else {
            $requestParams->setPaymentMethod(Inovarti_Pagarme_Model_Api::PAYMENT_METHOD_CREDITCARD);
            $requestParams->setCardHash($payment->getPagarmeCardHash());
            $requestParams->setInstallments($payment->getInstallments());
        }

        if ($this->getConfigData('async')) {
            $requestParams->setAsync(true);
            $requestParams->setPostbackUrl(Mage::getUrl('pagarme/transaction_creditcard/postback'));
        }

        $incrementId = $payment->getOrder()->getQuote()->getIncrementId();
        $requestParams->setMetadata(array('order_id' => $incrementId));
        return $requestParams;
    }
}
