<?php

namespace PayioLtd\Payio\Controller\Index;

use Magento\Framework\App\Action\Context;
use Magento\Quote\Model\QuoteIdMaskFactory;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\App\Action\HttpPostActionInterface as HttpPostActionInterface;

class Webhook extends \Magento\Framework\App\Action\Action implements HttpPostActionInterface
{
    /**
     * @var \Magento\Quote\Model\Quote
     */
    protected $quote = false;

    /**
     * @var QuoteIdMaskFactory
     */
    protected $quoteIdMaskFactory;

    /**
     * @var JsonFactory
     */
    protected $resultJsonFactory;

    /**
     * @var \PayioLtd\Payio\Model\CheckoutFactory $checkoutFactory
     */
    protected $checkoutFactory;

    /**
     * @var \Magento\Quote\Api\CartManagementInterface $cartManager
     */
    protected $cartManager;

    /**
     * @var \Magento\Framework\Session\Generic $session
     */
    protected $session;

    /**
     * @var \Magento\Checkout\Model\Session $checkoutSession
     */
    protected $checkoutSession;

    /**
     * @var CartRepositoryInterface $quoteRepository
     */
    protected $quoteRepository;

    /**
     * @var \Magento\Checkout\Helper\Data $checkoutData
     */
    protected $checkoutData;

    /**
     * @var \Magento\Customer\Model\Session $customerSession
     */
    protected $customerSession;

    /**
     * @var \Magento\Framework\App\ResponseInterface $response
     */
    protected $response;

    /**
     * \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
     */
    protected $scopeConfig;

    /**
     * Payment code
     *
     * @var string
     */
    protected $code = 'payio';

    /**
     * Webhook constructor.
     *
     * @param Context $context
     * @param QuoteIdMaskFactory $quoteIdMaskFactory
     * @param JsonFactory $resultJsonFactory
     * @param \PayioLtd\Payio\Model\CheckoutFactory $checkoutFactory
     * @param \Magento\Quote\Api\CartManagementInterface $cartManager
     * @param \Magento\Framework\Session\Generic $session
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param CartRepositoryInterface $quoteRepository
     * @param \Magento\Checkout\Helper\Data $checkoutData
     * @param \Magento\Customer\Model\Session $customerSession
     * @param \Magento\Framework\App\ResponseInterface $response
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        Context $context,
        QuoteIdMaskFactory $quoteIdMaskFactory,
        JsonFactory $resultJsonFactory,
        \PayioLtd\Payio\Model\CheckoutFactory $checkoutFactory,
        \Magento\Quote\Api\CartManagementInterface $cartManager,
        \Magento\Framework\Session\Generic $session,
        \Magento\Checkout\Model\Session $checkoutSession,
        CartRepositoryInterface $quoteRepository,
        \Magento\Checkout\Helper\Data $checkoutData,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Framework\App\ResponseInterface $response,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
    ) {
        parent::__construct($context);
        $this->quoteIdMaskFactory = $quoteIdMaskFactory;
        $this->checkoutFactory = $checkoutFactory;
        $this->cartManager = $cartManager;
        $this->session = $session;
        $this->checkoutSession = $checkoutSession;
        $this->quoteRepository = $quoteRepository;
        $this->checkoutData = $checkoutData;
        $this->customerSession = $customerSession;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->response = $response;
        $this->scopeConfig = $scopeConfig;
    }

    public function execute()
    {
        $resultJson = $this->resultJsonFactory->create();
        if (!$this->getRequest()->getContent()) {
            return $resultJson->setData(['message' => __('Post data cannot be empty')]);
        }

        $data = json_decode($this->getRequest()->getContent());

        if ((!$data->orderId) && empty($data->orderId)) {
            return $resultJson->setData(['message' => __('CartId not found')]);
        }

        if (is_numeric($data->orderId)) {
            $cartId = $data->orderId;
        } else {
            try {
                $quoteIdMask = $this->quoteIdMaskFactory->create()->load($data->orderId, 'masked_id');
                $cartId = $quoteIdMask->getQuoteId();
            } catch (NoSuchEntityException $exception) {
                return $resultJson->setData(['message' => __('Quote not found')]);
            }
        }

        $quote = $this->_getQuote($cartId);
        if (!$quote->getId()) {
            return $resultJson->setData(['message' => __('Quote not found')]);
        }
        $isActiveQuote = $quote->getIsActive();
        $payment = $quote->getPayment();
        $quote->setPaymentMethod($this->code);
        $quote->getPayment()->importData(['method' => $this->code]);
        $shippingAddress = $quote->getShippingAddress();
        $billingAddress  = $quote->getBillingAddress();

        $orderStatus = '';
        $orderStatusError = 1;
        if (!empty($orderStatus)) {
            if ($orderStatus == 'COMPLETED' || $orderStatus == 'PENDING') {
                $orderStatusError = 0;
            }
            if ($orderStatusError == 1) {
                return $resultJson->setData(['message' => __('Invalid order status found.')]);
            }
        }
        if (isset($data->status) && !empty($data->status)) {
            $orderStatus = $data->status;
        } else {
            if (isset($data->customerDetails) && !empty($data->customerDetails)) {
                $customerDetails = $data->customerDetails;
                if (isset($customerDetails->shippingPostcode) && isset($customerDetails->countryCode)) {
                    $shippingMethods = [];
                    $shippingMethodToUpdateQuote = [];
                    $quote->getShippingAddress()->setCountryId($customerDetails->countryCode);
                    $quote->getShippingAddress()->setPostcode($customerDetails->shippingPostcode);
                    $quote->getShippingAddress()->setCollectShippingRates(true);
                    $quote->getShippingAddress()->collectShippingRates();
                    $rates = $quote->getShippingAddress()->getShippingRatesCollection();
                    if (count($rates) > 0) {
                        $shippingMethodFound = 0;
                        foreach ($rates as $rate) {
                            $allowSpecific = $this->scopeConfig->getValue('carriers/' . $rate->getCarrier() . '/sallowspecific');
                            $specificCountry = '';
                            if ($allowSpecific == 1) {
                                $specificCountry = $this->scopeConfig->getValue('carriers/' . $rate->getCarrier() . '/specificcountry');
                            } else {
                                $shippingMethodFound = 1;
                            }
                            if ($specificCountry && strpos($specificCountry, ',') !== false) {
                                $specificShippingMethods = explode(',', $specificCountry);
                                if (in_array($customerDetails->countryCode, $specificShippingMethods)) {
                                    $shippingMethodFound = 1;
                                }
                            } else {
                                if ($specificCountry && $specificCountry == $customerDetails->countryCode) {
                                    $shippingMethodFound = 1;
                                }
                            }
                            if ($shippingMethodFound == 1) {
                                $shippingMethods[] = array(
                                    'rateId'     => $rate->getCode(),
                                    'methodId'   => $rate->getMethod(),
                                    'instanceId' => $rate->getCarrier(),
                                    'name'       => $rate->getCarrierTitle(),
                                    'cost'       => $rate->getPrice()
                                );
                                $shippingMethodToUpdateQuote[$rate->getCode()] = array(
                                    'rateId'     => $rate->getCode(),
                                    'name'       => $rate->getCarrierTitle(),
                                    'cost'       => $rate->getPrice()
                                );
                            }
                        }
                    }

                    if (!isset($customerDetails->firstName)) {
                        if (!empty($shippingMethods)) {
                            $temp = array_unique(array_column($shippingMethods, 'rateId'));
                            $shippingMethodUpdated = array_intersect_key($shippingMethods, $temp);
                            return $resultJson->setData($shippingMethodUpdated);
                        } else {
                            return $resultJson->setData([]);
                        }
                    }
                }
                if ($customerDetails->firstName) {
                    $shippingAddress->setFirstname($customerDetails->firstName);
                    $billingAddress->setFirstname($customerDetails->firstName);
                }
                if ($customerDetails->lastName) {
                    $shippingAddress->setLastname($customerDetails->lastName);
                    $billingAddress->setLastname($customerDetails->lastName);
                }
                if ($customerDetails->countryCode) {
                    $shippingAddress->setCountryId($customerDetails->countryCode);
                    $billingAddress->setCountryId($customerDetails->countryCode);
                }
                if ($customerDetails->email) {
                    $quote->setCustomerEmail($customerDetails->email);
                }
                if ($customerDetails->shippingAddress) {
                    $shippingAddress->setStreet($customerDetails->shippingAddress);
                    $billingAddress->setStreet($customerDetails->shippingAddress);
                }
                if ($customerDetails->phoneNumber) {
                    $shippingAddress->setTelephone($customerDetails->phoneNumber);
                    $billingAddress->setTelephone($customerDetails->phoneNumber);
                }
                if ($customerDetails->shippingPostcode) {
                    $shippingAddress->setPostcode($customerDetails->shippingPostcode);
                    $billingAddress->setPostcode($customerDetails->shippingPostcode);
                }
                if ($customerDetails->shippingCity) {
                    $shippingAddress->setCity($customerDetails->shippingCity);
                    $billingAddress->setCity($customerDetails->shippingCity);
                }
            }
            if (isset($data->selectedShippingId) && !empty($data->selectedShippingId)) {
                if (empty($shippingMethodToUpdateQuote) && !isset($shippingMethodToUpdateQuote[$data->selectedShippingId])) {
                    return $resultJson->setData(['message' => __('Shipping method not available.')]);
                }
                $shippingDetails = $shippingMethodToUpdateQuote[$data->selectedShippingId];
                $shippingAddress->setCollectShippingRates(true)->collectShippingRates()->setShippingMethod($data->selectedShippingId);
                $shippingAddress->setShippingDescription(trim($shippingDetails['name']));
                $shippingAddress->setShippingAmount($shippingDetails['cost']);
                $shippingAddress->setBaseShippingAmount($shippingDetails['cost']);
            }
        }

        if (isset($data->placeOrder) && $data->placeOrder == 0 && $isActiveQuote == 1) {
            $totals = array('totalTax' => $quote->getShippingAddress()->getTaxAmount(), 'totalAmount' => $quote->getGrandTotal() + $quote->getShippingAddress()->getShippingAmount());
            return $resultJson->setData($totals);
        }

        if ($this->getCheckoutMethod() == \Magento\Checkout\Model\Type\Onepage::METHOD_GUEST) {
            $this->prepareGuestQuote($cartId);
        }

        try {
            $checkout = $this->checkoutFactory->create();
            if ($isActiveQuote == 1) {
                $this->quoteRepository->save($quote->collectTotals());
                $orderId = $this->cartManager->placeOrder($quote->getId(), $payment);
            } else {
                $orderId = $quote->getReservedOrderId();
            }

            $orderObj = $checkout->process($orderId, $orderStatus, $isActiveQuote);
            $message = 'Order# ' . $orderId . ' placed Successfully.';
            if (isset($data->status) && !empty($data->status) && $isActiveQuote == 0) {
                return $resultJson->setData(['message' => __('Status Updated Successfully.'), 'publicOrderReference' => $orderObj->getIncrementId()]);
            }
            if (isset($data->selectedShippingId) && !empty($data->selectedShippingId)) {
                $totals = array('totalTax' => $orderObj->getTaxAmount(), 'totalAmount' => $orderObj->getGrandTotal(), 'publicOrderReference' => $orderObj->getIncrementId());
                return $resultJson->setData($totals);
            }
            return $resultJson->setData(['message' => $message, 'publicOrderReference' => $orderObj->getIncrementId()]);
        } catch (NoSuchEntityException $exception) {
            return $resultJson->setData(['message' => __('Error while placing an order.')]);
        }
    }

    /**
     * Get current quote
     *
     * @return \Magento\Quote\Api\Data\CartInterface|\Magento\Quote\Model\Quote
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function _getQuote($cartId)
    {
        if (!$this->quote) {
            if ($cartId) {
                $this->quote = $this->quoteRepository->get($cartId);
                $this->_getCheckoutSession()->replaceQuote($this->quote);
            } else {
                $this->quote = $this->_getCheckoutSession()->getQuote();
            }
        }
        return $this->quote;
    }

    /**
     * Prepare guest quote
     *
     * @return $this
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    protected function prepareGuestQuote($cartId)
    {
        $quote = $this->_getQuote($cartId);
        $quote->setCustomerId(null)
            ->setCustomerEmail($quote->getBillingAddress()->getEmail())
            ->setCustomerIsGuest(true)
            ->setCustomerGroupId(\Magento\Customer\Model\Group::NOT_LOGGED_IN_ID);
        return $this;
    }

    /**
     * Get Session object
     *
     * @return \Magento\Framework\Session\Generic
     */
    public function _getSession()
    {
        return $this->session;
    }

    /**
     * Get checkout session
     *
     * @return \Magento\Checkout\Model\Session
     */
    public function _getCheckoutSession()
    {
        return $this->checkoutSession;
    }

    /**
     * Generic method.
     *
     * @return string
     */
    public function getCheckoutMethod()
    {
        if ($this->_getCustomerSession()->isLoggedIn()) {
            return \Magento\Checkout\Model\Type\Onepage::METHOD_CUSTOMER;
        } else {
            return \Magento\Checkout\Model\Type\Onepage::METHOD_GUEST;
        }
    }

    /**
     * Get customer session
     *
     * @return \Magento\Customer\Model\Session
     */
    public function _getCustomerSession()
    {
        return $this->customerSession;
    }

    /**
     * Get response object
     *
     * @return \Magento\Framework\App\ResponseInterface
     */
    public function getResponse()
    {
        return $this->response;
    }
}
