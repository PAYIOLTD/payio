<?php

namespace PayioLtd\Payio\Block\Shortcut;

use Magento\Checkout\Model\Session;
use Magento\Catalog\Block\ShortcutInterface;
use Magento\Checkout\Model\DefaultConfigProvider;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\View\Element\Template\Context;
use Magento\Payment\Model\MethodInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Element\Template;
use PayioLtd\Payio\Helper\Data;

class Button extends Template implements ShortcutInterface
{
    /**#@+
     * Constants
     */
    const ALIAS_ELEMENT_INDEX       = 'alias';
    const BUTTON_ELEMENT_INDEX      = 'button_id';
    const BUTTON_DATA_ELEMENT_INDEX = 'data_id';
    const ENABLE_EXPRESS_CHECKOUT   = 'payment/payio/express_checkout';
    /**#@-*/

    /**
     * @var Session
     */
    private $checkoutSession;

    /**
     * @var MethodInterface
     */
    private $payment;

    /**
     * @var DefaultConfigProvider $defaultConfigProvider
     */
    private $defaultConfigProvider;

    /**
     * @var Data
     */
    private $helper;

    /**
     * Button constructor
     *
     * @param Context $context
     * @param Session $checkoutSession
     * @param MethodInterface $payment
     * @param DefaultConfigProvider $defaultConfigProvider
     * @param Data $helper
     * @param array $data
     * @throws InputException
     * @throws NoSuchEntityException
     */
    public function __construct(
        Context $context,
        Session $checkoutSession,
        MethodInterface $payment,
        DefaultConfigProvider $defaultConfigProvider,
        Data $helper,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->checkoutSession = $checkoutSession;
        $this->payment = $payment;
        $this->defaultConfigProvider = $defaultConfigProvider;
        $this->helper = $helper;
    }

    /**
     * @inheritdoc
     */
    protected function _toHtml(): string // @codingStandardsIgnoreLine
    {
        if ($this->isActive()) {
            return parent::_toHtml();
        }

        return '';
    }

    /**
     * @return bool
     */
    public function isActive(): bool
    {
        return $this->helper->getConfig(self::ENABLE_EXPRESS_CHECKOUT) && $this->payment->isAvailable($this->checkoutSession->getQuote());
    }

    /**
     * @inheritdoc
     */
    public function getAlias(): string
    {
        return $this->getData(self::ALIAS_ELEMENT_INDEX);
    }

    /**
     * @return string
     */
    public function getContainerId(): string
    {
        return $this->getData(self::BUTTON_ELEMENT_INDEX);
    }

    /**
     * @return string
     */
    public function getContainerDataId(): string
    {
        return $this->getData(self::BUTTON_DATA_ELEMENT_INDEX);
    }

    /**
     * Current Quote ID for guests
     *
     * @return string
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function getQuoteId(): string
    {
        try {
            $config = $this->defaultConfigProvider->getConfig();
            if (!empty($config['quoteData']['entity_id'])) {
                return $config['quoteData']['entity_id'];
            }
        } catch (NoSuchEntityException $e) {
            if ($e->getMessage() !== 'No such entity with cartId = ') {
                throw $e;
            }
        }

        return '';
    }

    /**
     * @return string
     */
    public function getExtraClassname(): string
    {
        return $this->getIsCart() ? 'cart' : 'minicart';
    }

    /**
     * @return string
     */
    public function getApiKey(): string
    {
        $getApiKey = '';
        if ($this->helper->getConfig('payment/payio/integration/api_key')) {
            $getApiKey = $this->helper->getConfig('payment/payio/integration/api_key');
        }
        return $getApiKey;
    }

    /**
     * @return string
     */
    public function getGatewayPath(): string
    {
         return $this->helper->getGatewayPath();
    }

    /**
     * @return string
     */
    public function getApiTransactionPath(): string
    {
        return $this->helper->getApiTransactionPath();
    }

    /**
     * @return string
     */
    public function getCheckoutUrl(): string
    {
        return $this->helper->getCheckoutUrl();
    }

    /**
     * @return string
     */
    public function getPaymentSuccessUrl(): string
    {
        return $this->helper->getPaymentSuccessUrl();
    }
}
