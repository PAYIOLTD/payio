<?php

namespace PayioLtd\Payio\Helper;

use \Magento\Framework\App\Helper\AbstractHelper;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Tax\Model\Calculation\RateFactory;

class Data extends AbstractHelper
{
    /**#@+
     * Constants
     */
    const PAYMENT_SUCCESS_URL = 'checkout/onepage/success/';
    const CHECKOUT_URL        = 'checkout';
    const GATEWAY_URL         = 'https://payio-test.nw.r.appspot.com/gateway';
    const TRANSACTION_PATH    = 'https://payio-test.nw.r.appspot.com/api/transaction/create';
    const API_SETTING_PATH    = 'https://payio-test.nw.r.appspot.com/api/merchant/setMerchantSettings';
    /**#@-*/

    /**
     * @var Magento\Store\Model\StoreManagerInterface;
     */
    protected $storeManager;

    /**
     * @var RateFactory
     */
    protected $rateFactory;

    /**
     * @var \Magento\Checkout\Model\Cart
     */
    protected $cart;

    /**
     * @var \Magento\Shipping\Model\Config $shipconfig
     */
    protected $shipconfig;

    /**
     * Data constructor.
     *
     * @param \Magento\Framework\App\Helper\Context $context
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param RateFactory $rateFactory
     * @param \Magento\Checkout\Model\Cart $cart,
     * @param \Magento\Shipping\Model\Config $shipconfig
     */
    public function __construct(
        \Magento\Framework\App\Helper\Context $context,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        RateFactory $rateFactory,
        \Magento\Checkout\Model\Cart $cart,
        \Magento\Shipping\Model\Config $shipconfig
    ) {
        parent::__construct($context);
        $this->storeManager = $storeManager;
        $this->rateFactory = $rateFactory;
        $this->cart = $cart;
        $this->shipconfig = $shipconfig;
    }

    /**
     * @return string
     */
    public function getConfig($config_path)
    {
        return $this->scopeConfig->getValue($config_path, \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
    }

    /**
     * Retrieve UK Tax Rate.
     *
     * @return string
     */
    public function getUKTaxRate()
    {
        $getUKTaxAmount = 0;
        /** @var \Magento\Tax\Model\ResourceModel\Calculation\Rate\Collection $collection */
        $collection = $this->rateFactory->create()->getCollection();
        $collection->addFieldToFilter('tax_country_id', 'GB');
        if ($collection->getSize()) {
            $taxRateUK = $collection->getData()[0]['rate'];
            if ($taxRateUK > 0) {
                $subTotal = $this->cart->getQuote()->getSubtotal();
                $getUKTaxAmount = ($subTotal / 100) * $taxRateUK;
            }
        }
        return $getUKTaxAmount;
    }

    /**
     * Retrieve all shipping method.
     *
     * @return array
     */
    public function getActiveShippingMethods()
    {
        $activeCarriers = $this->shipconfig->getActiveCarriers();
        $methods = [];
        foreach ($activeCarriers as $carrierCode => $carrierModel) {

            if ($carrierMethods = $carrierModel->getAllowedMethods()) {
                foreach ($carrierMethods as $methodCode => $method) {
                    $code = $carrierCode . '_' . $methodCode;
                    $carrierTitle = $this->scopeConfig->getValue('carriers/' . $carrierCode . '/title');
                    $cost = $this->scopeConfig->getValue('carriers/' . $carrierCode . '/price');
                    $allowSpecific = $this->scopeConfig->getValue('carriers/' . $carrierCode . '/sallowspecific');
                    $specificCountry = '';
                    if ($allowSpecific == 1) {
                        $specificCountry = $this->scopeConfig->getValue('carriers/' . $carrierCode . '/specificcountry');
                    }
                    if (empty($cost)) {
                        $cost = '0.00';
                    }
                    $methods[] = ['rateId' => $code, 'methodId' => $methodCode, 'instanceId' => $carrierCode,  'name' => $carrierTitle, 'cost' => $cost, 'countryCode' => $specificCountry];
                }
            }
        }

        return $methods;
    }

    /**
     * @return object
     */
    public function getStoreBase()
    {
        return $this->storeManager->getStore();
    }

    /**
     * @return string
     */
    public function getStoreBaseURL(): string
    {
        return $this->getStoreBase()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_WEB);
    }

    /**
     * @return string
     */
    public function getBaseUrlMedia()
    {
        return $this->getStoreBase()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA);
    }

    /**
     * @return string
     */
    public function getCheckoutUrl(): string
    {
        return $this->getStoreBaseUrl() . self::CHECKOUT_URL;
    }

    /**
     * @return string
     */
    public function getPaymentSuccessUrl(): string
    {
        return $this->getStoreBaseUrl() . self::PAYMENT_SUCCESS_URL;
    }

    /**
     * @return string
     */
    public function getGatewayPath(): string
    {
        return self::GATEWAY_URL;
    }

    /**
     * @return string
     */
    public function getApiTransactionPath(): string
    {
        return self::TRANSACTION_PATH;
    }

    /**
     * @return string
     */
    public function getApiSettingPath(): string
    {
        return self::API_SETTING_PATH;
    }

    /**
     * @return string
     */
    public function getCurrentCurrencyCode()
    {
        return $this->getStoreBase()->getCurrentCurrencyCode();
    }
}
