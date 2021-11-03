<?php

namespace PayioLtd\Payio\Model;

/**
 * Pay In Store payment method model
 */
class Payio extends \Magento\Payment\Model\Method\AbstractMethod
{

    /**
     * Payment code
     *
     * @var string
     */
    protected $_code = 'payio';

    /**
     * Availability option
     *
     * @var bool
     */
    protected $_isOffline = true;
}
