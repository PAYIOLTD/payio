<?php

namespace PayioLtd\Payio\Model;

class Checkout
{
    /**
     * @var \Magento\Sales\Api\OrderManagementInterface $orderManager
     */
    protected $orderManager;

    /**
     * @var \Magento\Framework\Serialize\Serializer\Json $serializer
     */
    protected $serializer;

    /**
     * @var \Magento\Sales\Model\Order\Status\HistoryFactory $historyFactory
     */
    protected $historyFactory;

    /**
     * @var \Magento\Sales\Api\OrderStatusHistoryRepositoryInterface $orderStatusHistoryRepository
     */
    protected $orderStatusHistoryRepository;

    /**
     * @var \Magento\Sales\Api\OrderRepositoryInterface $orderRepository
     */
    protected $orderRepository;

    /**
     * @var \Magento\Sales\Api\InvoiceOrderInterface $invoiceOrder
     */
    protected $invoiceOrder;

    /**
     * @var \Magento\Sales\Model\OrderFactory $orderFactory
     */
    protected $orderFactory;

    /**
     * Checkout constructor.
     *
     * @param \Magento\Framework\Serialize\Serializer\Json $serializer
     * @param \Magento\Sales\Api\OrderManagementInterface $orderManager
     * @param \Magento\Sales\Model\Order\Status\HistoryFactory $historyFactory
     * @param \Magento\Sales\Api\OrderStatusHistoryRepositoryInterface $orderStatusHistoryRepository
     * @param \Magento\Sales\Api\OrderRepositoryInterface $orderRepository
     * @param \Magento\Sales\Api\InvoiceOrderInterface $invoiceOrder
     * @param \Magento\Sales\Model\OrderFactory $orderFactory
     */
    public function __construct(
        \Magento\Framework\Serialize\Serializer\Json  $serializer,
        \Magento\Sales\Api\OrderManagementInterface $orderManager,
        \Magento\Sales\Model\Order\Status\HistoryFactory  $historyFactory,
        \Magento\Sales\Api\OrderStatusHistoryRepositoryInterface $orderStatusHistoryRepository,
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository,
        \Magento\Sales\Api\InvoiceOrderInterface $invoiceOrder,
        \Magento\Sales\Model\OrderFactory $orderFactory
    ) {
        $this->serializer = $serializer;
        $this->orderManager = $orderManager;
        $this->historyFactory = $historyFactory;
        $this->orderStatusHistoryRepository = $orderStatusHistoryRepository;
        $this->orderRepository = $orderRepository;
        $this->invoiceOrder = $invoiceOrder;
        $this->orderFactory = $orderFactory;
    }

    /**
     * Process order
     *
     * @param int $orderId
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     */
    public function process($orderId, $orderStatus, $isActiveQuote)
    {
        if ($isActiveQuote == 1) {
            $order = $this->orderRepository->get($orderId);
        } else {
            $order = $this->orderFactory->create()->loadByIncrementId($orderId);
        }

        $orderId = $order->getId();
        $payment = $order->getPayment();
        $additionalInfo = $payment->getAdditionalInformation();

        if (isset($additionalInfo['error'])) {
            $errors = $this->serializer->unserialize($additionalInfo['error']);
            foreach ($errors as $error) {
                $this->addCommentsHistory($orderId, $this->serializer->serialize($error));
            }
            $this->orderManager->hold($orderId);
        } else {
            $comment = '';
            if (!empty($orderStatus)) {
                if ($orderStatus == 'PENDING') {
                    $orderStatus = \Magento\Sales\Model\Order::STATE_PENDING_PAYMENT;
                    $comment = 'Order is on hold payment is pending';
                }
                if ($orderStatus == 'COMPLETED') {
                    $orderStatus = \Magento\Sales\Model\Order::STATE_PROCESSING;
                    $comment = 'Captured Amount ' . $order->getGrandTotal();
                    $this->invoiceOrder->execute($order->getId());
                }
            } else {
                $orderStatus = \Magento\Sales\Model\Order::STATE_PROCESSING;
                $comment = 'New Order Placed';
            }

            $this->addCommentsHistory(
                $orderId,
                __($comment),
                false,
                $orderStatus
            );
        }

        return $order;
    }

    /**
     * Add order comments
     *
     * @param int $orderId
     * @param string $message
     * @param bool $notify
     * @param string $status
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     */
    public function addCommentsHistory($orderId, $message, $notify = false, $status = \Magento\Sales\Model\Order::STATE_HOLDED)
    {
        $history = $this->historyFactory->create();
        $history->setParentId($orderId)
            ->setIsCustomerNotified($notify)
            ->setStatus($status)
            ->setComment($message);
        $this->orderStatusHistoryRepository->save($history);
        $this->orderManager->addComment($orderId, $history);
    }
}
