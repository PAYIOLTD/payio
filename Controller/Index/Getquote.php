<?php

namespace PayioLtd\Payio\Controller\Index;

use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\Action;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Model\QuoteIdMaskFactory;
use Magento\Catalog\Helper\Product\ConfigurationPool;
use Magento\Customer\Api\AddressMetadataInterface;
use Magento\Customer\Api\CustomerRepositoryInterface as CustomerRepository;
use Magento\Customer\Model\Context as CustomerContext;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Http\Context as HttpContext;
use Magento\Framework\App\ObjectManager;
use Magento\Quote\Api\CartItemRepositoryInterface as QuoteItemRepository;
use Magento\Quote\Api\CartTotalRepositoryInterface;
use Magento\Quote\Api\Data\AddressInterface;
use Magento\Quote\Api\ShippingMethodManagementInterface as ShippingMethodManager;
use Magento\Ui\Component\Form\Element\Multiline;
use PayioLtd\Payio\Helper\Data as PayioHelper;

class Getquote extends Action
{

    /**
     * @var QuoteIdMaskFactory
     */
    protected $quoteIdMaskFactory;

    /**
     * @var AttributeOptionManagementInterface
     */
    protected $attributeOptionManager;

    /**
     * @var CustomerRepository
     */
    protected $customerRepository;

    /**
     * @var CustomerSession
     */
    protected $customerSession;

    /**
     * @var CustomerUrlManager
     */
    protected $customerUrlManager;

    /**
     * @var HttpContext
     */
    protected $httpContext;

    /**
     * @var \Magento\Quote\Api\CartRepositoryInterface
     */
    protected $quoteRepository;

    /**
     * @var QuoteItemRepository
     */
    protected $quoteItemRepository;

    /**
     * @var ConfigurationPool
     */
    protected $configurationPool;

    /**
     * @var \Magento\Customer\Model\Address\Mapper
     */
    protected $addressMapper;

    /**
     * @var \Magento\Customer\Model\Address\Config
     */
    protected $addressConfig;

    /**
     * @var \Magento\Catalog\Helper\Image
     */
    protected $imageHelper;

    /**
     * @var CartTotalRepositoryInterface
     */
    protected $cartTotalRepository;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var AddressMetadataInterface
     */
    protected $addressMetadata;

    /**
     * @var ShippingMethodManager
     */
    protected $shippingMethodManager;

    /**
     * @var PayioHelper
     */
    protected $payioHelper;

    /**
     * @param Context $context
     * @param CustomerRepository $customerRepository
     * @param CustomerSession $customerSession
     * @param HttpContext $httpContext
     * @param \Magento\Quote\Api\CartRepositoryInterface $quoteRepository
     * @param QuoteItemRepository $quoteItemRepository
     * @param ShippingMethodManager $shippingMethodManager
     * @param ConfigurationPool $configurationPool
     * @param QuoteIdMaskFactory $quoteIdMaskFactory
     * @param \Magento\Customer\Model\Address\Mapper $addressMapper
     * @param \Magento\Customer\Model\Address\Config $addressConfig
     * @param \Magento\Catalog\Helper\Image $imageHelper
     * @param CartTotalRepositoryInterface $cartTotalRepository
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param AddressMetadataInterface $addressMetadata
     * @param PayioHelper $payioHelper
     * @codeCoverageIgnore
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        Context $context,
        CustomerRepository $customerRepository,
        CustomerSession $customerSession,
        HttpContext $httpContext,
        \Magento\Quote\Api\CartRepositoryInterface $quoteRepository,
        QuoteItemRepository $quoteItemRepository,
        ConfigurationPool $configurationPool,
        \Magento\Customer\Model\Address\Mapper $addressMapper,
        \Magento\Customer\Model\Address\Config $addressConfig,
        \Magento\Catalog\Helper\Image $imageHelper,
        CartTotalRepositoryInterface $cartTotalRepository,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        QuoteIdMaskFactory $quoteIdMaskFactory,
        ShippingMethodManager $shippingMethodManager,
        PayioHelper $payioHelper,
        AddressMetadataInterface $addressMetadata = null
    ) {
        parent::__construct($context);
        $this->customerRepository = $customerRepository;
        $this->customerSession = $customerSession;
        $this->httpContext = $httpContext;
        $this->quoteRepository = $quoteRepository;
        $this->quoteItemRepository = $quoteItemRepository;
        $this->configurationPool = $configurationPool;
        $this->addressMapper = $addressMapper;
        $this->addressConfig = $addressConfig;
        $this->imageHelper = $imageHelper;
        $this->cartTotalRepository = $cartTotalRepository;
        $this->storeManager = $storeManager;
        $this->quoteIdMaskFactory = $quoteIdMaskFactory;
        $this->shippingMethodManager = $shippingMethodManager;
        $this->payioHelper = $payioHelper;
        $this->addressMetadata = $addressMetadata ?: ObjectManager::getInstance()->get(AddressMetadataInterface::class);
    }

    public function execute()
    {
        $output = [];
        $cartId = '';
        $maskedHashId = $this->getRequest()->getParams('maskid');
        if ($this->isCustomerLoggedIn()) {
            $cartId = $maskedHashId['maskid'];
        } else {
            try {
                $quoteIdMask = $this->quoteIdMaskFactory->create()->load($maskedHashId, 'masked_id');
                $cartId = $quoteIdMask->getQuoteId();
            } catch (NoSuchEntityException $exception) {
                return null;
            }
        }

        if ($cartId) {
            $quote = $this->quoteRepository->get($cartId);
            $quoteId = $quote->getId();
            $email = $quote->getShippingAddress()->getEmail();
            $quoteItemData = $this->getQuoteItemData($quoteId);
            $getQuoteData = $this->getQuoteData($quoteId);
            $output['customerData'] = $this->getCustomerData();
            $output['quoteData'] = $getQuoteData;
            $output['quoteItemData'] = $quoteItemData;
            $output['selectedShippingMethod'] = $this->getSelectedShippingMethod($quoteId);
            $output['isCustomerLoggedIn'] = $this->isCustomerLoggedIn();
            if (!$this->isCustomerLoggedIn()) {
                $shippingAddressFromData = $this->getAddressFromData($quote->getShippingAddress());
                $billingAddressFromData = $this->getAddressFromData($quote->getBillingAddress());
                $output['shippingAddressFromData'] = $shippingAddressFromData;
                if ($shippingAddressFromData != $billingAddressFromData) {
                    $output['billingAddressFromData'] = $billingAddressFromData;
                }
                $output['validatedEmailValue'] = $email;
            }
            $output['totalsData'] = $this->getTotalsData($quoteId);
            $output['currencyCode'] = $this->storeManager->getStore()->getCurrentCurrencyCode();
            $output['cartTax'] = $this->payioHelper->getUKTaxRate();
        }

        $response = $this->resultFactory
            ->create(\Magento\Framework\Controller\ResultFactory::TYPE_JSON)
            ->setData($output);

        return $response;
    }

    /**
     * Retrieve quote item data.
     *
     * @return array
     */
    protected function getQuoteItemData($quoteId)
    {
        $quoteItemData = [];
        if ($quoteId) {
            $quoteItems = $this->quoteItemRepository->getList($quoteId);
            foreach ($quoteItems as $index => $quoteItem) {
                $quoteItemData[$index] = $quoteItem->toArray();
                $quoteItemData[$index]['options'] = $this->getFormattedOptionValue($quoteItem);
                $quoteItemData[$index]['thumbnail'] = $this->imageHelper->init(
                    $quoteItem->getProduct(),
                    'product_thumbnail_image'
                )->getUrl();
                $quoteItemData[$index]['message'] = $quoteItem->getMessage();
            }
        }
        return $quoteItemData;
    }

    /**
     * Retrieve formatted item options view.
     *
     * @param \Magento\Quote\Api\Data\CartItemInterface $item
     * @return array
     */
    protected function getFormattedOptionValue($item)
    {
        $optionsData = [];
        $options = $this->configurationPool->getByProductType($item->getProductType())->getOptions($item);
        foreach ($options as $index => $optionValue) {
            /* @var $helper \Magento\Catalog\Helper\Product\Configuration */
            $helper = $this->configurationPool->getByProductType('default');
            $params = [
                'max_length' => 55,
                'cut_replacer' => ' <a href="#" class="dots tooltip toggle" onclick="return false">...</a>'
            ];
            $option = $helper->getFormattedOptionValue($optionValue, $params);
            $optionsData[$index] = $option;
            $optionsData[$index]['label'] = $optionValue['label'];
        }
        return $optionsData;
    }

    /**
     * Retrieve quote data.
     *
     * @return array
     */
    protected function getQuoteData($quoteId)
    {
        $quoteData = [];
        if ($quoteId) {
            $quote = $this->quoteRepository->get($quoteId);
            $quoteData = $quote->toArray();
            $quoteData['is_virtual'] = $quote->getIsVirtual();
        }
        return $quoteData;
    }

    /**
     * Retrieve customer data.
     *
     * @return array
     */
    protected function getCustomerData()
    {
        $customerData = [];
        if ($this->isCustomerLoggedIn()) {
            $customer = $this->customerRepository->getById($this->customerSession->getCustomerId());
            $customerData = $customer->__toArray();
            foreach ($customer->getAddresses() as $key => $address) {
                $customerData['addresses'][$key]['inline'] = $this->getCustomerAddressInline($address);
                if ($address->getCustomAttributes()) {
                    $customerData['addresses'][$key]['custom_attributes'] = $this->filterNotVisibleAttributes(
                        $customerData['addresses'][$key]['custom_attributes']
                    );
                }
            }
        }
        return $customerData;
    }

    /**
     * Set additional customer address data.
     *
     * @param \Magento\Customer\Api\Data\AddressInterface $address
     * @return string
     */
    protected function getCustomerAddressInline($address)
    {
        $builtOutputAddressData = $this->addressMapper->toFlatArray($address);
        return $this->addressConfig
            ->getFormatByCode(\Magento\Customer\Model\Address\Config::DEFAULT_ADDRESS_FORMAT)
            ->getRenderer()
            ->renderArray($builtOutputAddressData);
    }

    /**
     * Filter not visible on storefront custom attributes.
     *
     * @param array $attributes
     * @return array
     */
    protected function filterNotVisibleAttributes(array $attributes)
    {
        $attributesMetadata = $this->addressMetadata->getAllAttributesMetadata();
        foreach ($attributesMetadata as $attributeMetadata) {
            if (!$attributeMetadata->isVisible()) {
                unset($attributes[$attributeMetadata->getAttributeCode()]);
            }
        }

        return $this->setLabelsToAttributes($attributes);
    }

    /**
     * Check if customer is logged in.
     *
     * @return bool
     * @codeCoverageIgnore
     */
    protected function isCustomerLoggedIn()
    {
        return (bool)$this->httpContext->getValue(CustomerContext::CONTEXT_AUTH);
    }

    /* Create address data appropriate to fill checkout address form.
     *
     * @param AddressInterface $address
     * @return array
     * @throws \Magento\Framework\Exception\LocalizedException
     */

    protected function getAddressFromData(AddressInterface $address): array
    {
        $addressData = [];
        $attributesMetadata = $this->addressMetadata->getAllAttributesMetadata();
        foreach ($attributesMetadata as $attributeMetadata) {
            if (!$attributeMetadata->isVisible()) {
                continue;
            }
            $attributeCode = $attributeMetadata->getAttributeCode();
            $attributeData = $address->getData($attributeCode);
            if ($attributeData) {
                if ($attributeMetadata->getFrontendInput() === Multiline::NAME) {
                    $attributeData = is_array($attributeData) ? $attributeData : explode("\n", $attributeData);
                    $attributeData = (object)$attributeData;
                }
                if ($attributeMetadata->isUserDefined()) {
                    $addressData[CustomAttributesDataInterface::CUSTOM_ATTRIBUTES][$attributeCode] = $attributeData;
                    continue;
                }
                $addressData[$attributeCode] = $attributeData;
            }
        }

        return $addressData;
    }

    /**
     * Return quote totals data.
     *
     * @return array
     */
    protected function getTotalsData($quoteId)
    {
        /** @var \Magento\Quote\Api\Data\TotalsInterface $totals */
        $totals = $this->cartTotalRepository->get($quoteId);
        $items = [];
        /** @var  \Magento\Quote\Model\Cart\Totals\Item $item */
        foreach ($totals->getItems() as $item) {
            $items[] = $item->__toArray();
        }
        $totalSegmentsData = [];
        /** @var \Magento\Quote\Model\Cart\TotalSegment $totalSegment */
        foreach ($totals->getTotalSegments() as $totalSegment) {
            $totalSegmentArray = $totalSegment->toArray();
            if (is_object($totalSegment->getExtensionAttributes())) {
                $totalSegmentArray['extension_attributes'] = $totalSegment->getExtensionAttributes()->__toArray();
            }
            $totalSegmentsData[] = $totalSegmentArray;
        }
        $totals->setItems($items);
        $totals->setTotalSegments($totalSegmentsData);
        $totalsArray = $totals->toArray();
        if (is_object($totals->getExtensionAttributes())) {
            $totalsArray['extension_attributes'] = $totals->getExtensionAttributes()->__toArray();
        }
        return $totalsArray;
    }

    /* Retrieve selected shipping method.
     *
     * @return array|null
     */
    protected function getSelectedShippingMethod($quoteId)
    {
        $shippingMethodData = null;
        try {
            $shippingMethod = $this->shippingMethodManager->get($quoteId);
            if ($shippingMethod) {
                $shippingMethodData = $shippingMethod->__toArray();
            }
        } catch (\Exception $exception) {
            $shippingMethodData = null;
        }
        return $shippingMethodData;
    }
}
