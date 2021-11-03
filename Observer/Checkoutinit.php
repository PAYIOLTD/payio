<?php

namespace PayioLtd\Payio\Observer;

class Checkoutinit implements \Magento\Framework\Event\ObserverInterface
{
    /**
     * Checkoutinit
     *
     * @param Observer $observer
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $address = $observer->getData('address');
        return $address;
    }
}
