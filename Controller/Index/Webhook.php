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
        \Magento\Framework\App\ResponseInterface $response
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

        try {
            $quoteIdMask = $this->quoteIdMaskFactory->create()->load($data->orderId, 'masked_id');
            $cartId = $quoteIdMask->getQuoteId();
        } catch (NoSuchEntityException $exception) {
            return $resultJson->setData(['message' => __('Quote not found')]);
        }

        $quote = $this->_getQuote($cartId);
        $isActiveQuote = $quote->getIsActive();
        $payment = $quote->getPayment();
        $quote->setPaymentMethod($this->code);
        $quote->getPayment()->importData(['method' => $this->code]);
        $shippingAddress = $quote->getShippingAddress();
        $billingAddress  = $quote->getBillingAddress();

        if (!$quote->getId()) {
            return $resultJson->setData(['message' => __('Quote not found')]);
        }

        $orderStatus = '';
        $orderStatusError = 1;
        if (isset($data->status) && !empty($data->status)) {
            $orderStatus = $data->status;
        } else {
            if (isset($data->customerDetails) && !empty($data->customerDetails)) {
                $customerDetails = $data->customerDetails;

                if ($customerDetails->firstName) {
                    $shippingAddress->setFirstname($customerDetails->firstName);
                    $billingAddress->setFirstname($customerDetails->firstName);
                }
                if ($customerDetails->lastName) {
                    $shippingAddress->setLastname($customerDetails->lastName);
                    $billingAddress->setLastname($customerDetails->lastName);
                }
                if ($customerDetails->country) {
                    $shippingAddress->setCountryId($customerDetails->country);
                    $billingAddress->setCountryId($customerDetails->country);
                }
                if ($customerDetails->email) {
                    $quote->setCustomerEmail($customerDetails->email);
                }
                if ($customerDetails->shippingAddress) {
                    $shippingAddress->setStreet($customerDetails->shippingAddress);
                    $billingAddress->setStreet($customerDetails->country);
                }
                if ($customerDetails->phoneNumber) {
                    $shippingAddress->setTelephone($customerDetails->phoneNumber);
                    $billingAddress->setTelephone($customerDetails->phoneNumber);
                }
                if ($customerDetails->shippingPostcode) {
                    $shippingAddress->setPostcode($customerDetails->shippingPostcode);
                    $billingAddress->setTelephone($customerDetails->phoneNumber);
                }
                if ($customerDetails->shippingCity) {
                    $shippingAddress->setCity($customerDetails->shippingCity);
                    $billingAddress->setCity($customerDetails->shippingCity);
                }
            }

            if (isset($data->shipping) && !empty($data->shipping)) {
                $shippingDetails = $data->shipping;
                if ($shippingDetails->id) {
                    $shippingAddress->setCollectShippingRates(true)->collectShippingRates()->setShippingMethod($shippingDetails->id);
                    if ($shippingDetails->name) {
                        $shippingAddress->setShippingDescription(trim($shippingDetails->name));
                    }
                    if ($shippingDetails->cost) {
                        $quote->setShippingAmount($shippingDetails->cost);
                        $quote->setBaseShippingAmount($shippingDetails->cost);
                    }
                }
            }
        }

        if ($this->getCheckoutMethod() == \Magento\Checkout\Model\Type\Onepage::METHOD_GUEST) {
            $this->prepareGuestQuote($cartId);
        }

        try {
            $checkout = $this->checkoutFactory->create();
            if ($isActiveQuote == 1) {
                $orderId = $this->cartManager->placeOrder($quote->getId(), $payment);
            } else {
                $orderId = $quote->getReservedOrderId();
            }
            if (!empty($orderStatus)) {
                if ($orderStatus == 'COMPLETED' || $orderStatus == 'PENDING') {
                    $orderStatusError = 0;
                }
                if ($orderStatusError == 1) {
                    return $resultJson->setData(['message' => __('Invalid order status found.')]);
                }
            }

            $orderObj = $checkout->process($orderId, $orderStatus, $isActiveQuote);
            $message = 'Order# ' . $orderId . ' placed Successfully.';
            if (isset($data->status) && !empty($data->status) && $isActiveQuote == 0) {
                return $resultJson->setData(['message' => __('Status Updated Successfully.'), 'orderId' => $orderObj->getIncrementId()]);
            }
            return $resultJson->setData(['message' => $message, 'orderId' => $orderObj->getIncrementId()]);
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
