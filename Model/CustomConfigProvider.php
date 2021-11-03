<?php

namespace PayioLtd\Payio\Model;

use Magento\Checkout\Model\ConfigProviderInterface;
use \PayioLtd\Payio\Helper\Data;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Class ConfigProvider
 */
class CustomConfigProvider implements ConfigProviderInterface
{
    /**#@+
     * Constants
     */
    const CODE = 'payio';
    /**#@-*/

    /**
     * @var \PayioLtd\Payio\Helper\Data
     */
    protected $helper;

    /**
     * Payment ConfigProvider constructor.
     *
     * @param \Magento\Payment\Helper\Data $paymentHelper
     */
    public function __construct(
        Data $helper
    ) {
        $this->helper = $helper;
    }

    /**
     * Retrieve assoc array of checkout configuration
     *
     * @return array
     */
    public function getConfig()
    {
        return [
            'payment' => [
                self::CODE => [
                    // get setting values using events.xml section/group/field ids for path
                    'apiKey' => $this->helper->getConfig('payment/payio/integration/api_key'),
                    'apiTransactionPath' => $this->helper->getApiTransactionPath(),
                    'apiSettingsPath' => $this->helper->getApiSettingPath(),
                    'gatewayPath' => $this->helper->getGatewayPath(),
                    'checkoutUrl' => $this->helper->getCheckoutUrl(),
                    'paymentSuccessUrl' => $this->helper->getPaymentSuccessUrl(),
                    'currency' => $this->helper->getCurrentCurrencyCode(),
                    'cartTax' => $this->helper->getUKTaxRate()
                ]
            ]
        ];
    }
}
