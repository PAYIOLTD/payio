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
    const GATEWAY_URL         = 'https://secure.payio.co.uk/gateway';
    const TRANSACTION_PATH    = 'https://secure.payio.co.uk/api/transaction/create';
    const API_SETTING_PATH    = 'https://secure.payio.co.uk/api/merchant/setMerchantSettings';
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
     * Data constructor.
     *
     * @param \Magento\Framework\App\Helper\Context $context
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param RateFactory $rateFactory
     * @param \Magento\Checkout\Model\Cart $cart,
     */
    public function __construct(
        \Magento\Framework\App\Helper\Context $context,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        RateFactory $rateFactory,
        \Magento\Checkout\Model\Cart $cart
    ) {
        parent::__construct($context);
        $this->storeManager = $storeManager;
        $this->rateFactory = $rateFactory;
        $this->cart = $cart;
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
