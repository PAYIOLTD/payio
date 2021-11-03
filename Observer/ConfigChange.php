<?php

namespace PayioLtd\Payio\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer as EventObserver;
use Magento\Framework\HTTP\ZendClient;
use Magento\Framework\Message\ManagerInterface;
use \PayioLtd\Payio\Helper\Data;

class ConfigChange implements ObserverInterface
{
    /**
     * @var Magento\Framework\HTTP\ZendClient
     */
    protected $zend;

    /**
     * @var Magento\Framework\Message\ManagerInterface;
     */
    protected $messageManager;

    /**
     * @var \PayioLtd\Payio\Helper\Data;
     */
    protected $helper;

    /**
     * ConfigChange constructor.
     * @param ZendClient $zend
     * @param ManagerInterface $messageManager
     * @param Data $helper
     */
    public function __construct(
        ZendClient $zend,
        ManagerInterface $messageManager,
        Data $helper
    ) {

        $this->zend = $zend;
        $this->messageManager = $messageManager;
        $this->helper = $helper;
    }

    /**
     * ConfigChange
     *
     * @param EventObserver $observer
     */
    public function execute(EventObserver $observer)
    {
        $apiKey = $this->helper->getConfig('payment/payio/integration/api_key');

        $apiPath = $this->helper->getApiSettingPath();
        $urlMedia = $this->helper->getBaseUrlMedia();
        $sandboxed = $this->helper->getConfig('payment/payio/test_mode');
        $pluginActive = $this->helper->getConfig('payment/payio/active');
        $data = [
            "buttonMainColor"        =>  '#' . $this->helper->getConfig('payment/payio/design/brand_color'),
            "logoUrl"                =>  $urlMedia . 'payments/logo/' . $this->helper->getConfig('payment/payio/design/logo'),
            "logoText"               =>  $this->helper->getConfig('payment/payio/design/logo_alt'),
            "sandboxed"              =>  $sandboxed,
            "pluginActive"           =>  $pluginActive,
            'platformBackendBaseUrl' =>  $this->helper->getStoreBaseURL()
        ];

        try {
            $this->zend->setUri($apiPath);
            $this->zend->setHeaders('X-API-KEY', $apiKey);
            $this->zend->setMethod(\Zend_Http_Client::PUT);
            $this->zend->setRawData(json_encode($data), 'application/json');
            $response = $this->zend->request();
        } catch (\Exception $e) {
            $this->messageManager->addError(__($e->getMessage()));
        }
    }
}
