<?php
namespace Ovesio\Ecommerce\Model\Export;

use Magento\Sales\Model\ResourceModel\Order\CollectionFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Stdlib\DateTime\DateTime;

class Orders
{
    /**
     * @var CollectionFactory
     */
    protected $orderCollectionFactory;

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var DateTime
     */
    protected $date;

    public function __construct(
        CollectionFactory $orderCollectionFactory,
        ScopeConfigInterface $scopeConfig,
        DateTime $date
    ) {
        $this->orderCollectionFactory = $orderCollectionFactory;
        $this->scopeConfig = $scopeConfig;
        $this->date = $date;
    }

    public function export($durationMonths)
    {
        if ($durationMonths <= 0) {
            $durationMonths = 12;
        }

        // Calculate Date From
        $dateFrom = date('Y-m-d', strtotime("-$durationMonths months"));

        $collection = $this->orderCollectionFactory->create();
        $collection->addAttributeToSelect('*');
        $collection->addAttributeToFilter('created_at', ['gteq' => $dateFrom]);

        // Filter by Status
        $statuses = $this->scopeConfig->getValue('ovesio_ecommerce/general/order_statuses');
        if ($statuses) {
            $statuses = explode(',', $statuses);
            $collection->addAttributeToFilter('status', ['in' => $statuses]);
        }

        $data = [];

        foreach ($collection as $order) {
            $orderId = $order->getEntityId(); // Or increment_id? Presta used id_order. M2 uses entity_id internally. I'll use entity_id.
            // Wait, Presta code: order_id = id_order.
            // Using Increment ID might be more useful for user reference, but code uses ID. stick to entity_id to be safe, or just increment_id.
            // Prestashop ID is integer. M2 Entity ID is integer. Increment ID is string.
            // I'll use Entity ID.

            // Get Email Hash
            $email = $order->getCustomerEmail();
            $customerIdHash = md5($email);

            // Currency - Use Base
            $currency = $order->getBaseCurrencyCode();
            $total = (float)$order->getBaseGrandTotal();

            // Products
            $products = [];
            foreach ($order->getAllVisibleItems() as $item) {
                $products[] = [
                    'sku' => $item->getSku(),
                    'name' => $item->getName(),
                    'quantity' => (int)$item->getQtyOrdered(),
                    'price' => (float)$item->getBasePriceInclTax() // Unit price tax incl in base currency
                ];
            }

            $data[] = [
                'order_id' => $orderId,
                'customer_id' => $customerIdHash,
                'total' => $total,
                'currency' => $currency,
                'date' => $order->getCreatedAt(),
                'products' => $products
            ];
        }

        return $data;
    }
}
