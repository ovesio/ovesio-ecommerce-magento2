<?php
namespace Ovesio\Ecommerce\Controller\Feed;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Ovesio\Ecommerce\Model\Export\Orders as OrdersExport;
use Ovesio\Ecommerce\Model\Export\Products as ProductsExport;

class Index extends Action
{
    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var JsonFactory
     */
    protected $resultJsonFactory;

    /**
     * @var OrdersExport
     */
    protected $ordersExport;

    /**
     * @var ProductsExport
     */
    protected $productsExport;

    /**
     * @param Context $context
     * @param ScopeConfigInterface $scopeConfig
     * @param JsonFactory $resultJsonFactory
     * @param OrdersExport $ordersExport
     * @param ProductsExport $productsExport
     */
    public function __construct(
        Context $context,
        ScopeConfigInterface $scopeConfig,
        JsonFactory $resultJsonFactory,
        OrdersExport $ordersExport,
        ProductsExport $productsExport
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->ordersExport = $ordersExport;
        $this->productsExport = $productsExport;
        parent::__construct($context);
    }

    public function execute()
    {
        $result = $this->resultJsonFactory->create();

        // Check availability
        $isActive = $this->scopeConfig->getValue('ovesio_ecommerce/general/active');
        if (!$isActive) {
             return $result->setData(['error' => __('Module is disabled')]);
        }

        // Validate Hash
        $configHash = $this->scopeConfig->getValue('ovesio_ecommerce/general/hash');
        $requestHash = $this->getRequest()->getParam('hash');

        if (!$configHash || $requestHash !== $configHash) {
            return $result->setData(['error' => __('Access denied: Invalid Hash')]);
        }

        // Action
        $action = $this->getRequest()->getParam('action', 'products');

        $data = [];
        if ($action == 'orders') {
            $duration = (int)$this->scopeConfig->getValue('ovesio_ecommerce/general/export_duration');
            $data = $this->ordersExport->export($duration);
            $type = 'orders';
        } else {
            $data = $this->productsExport->export();
            $type = 'products';
        }

        // File Download Headers (optional, but requested in original code: Content-Disposition)
        // Since we return JSON result directly, Magento sets headers.
        // We can add the disposition header manually to the response object if really needed.
        // The original code did `outputJson` with headers and `die`.
        // In M2, proper way is returning resultJson.
        // If they want it to trigger download, we can set header.

        // $this->getResponse()->setHeader('Content-Disposition', 'attachment; filename="export_' . $type . '_' . date('Y-m-d') . '.json"', true);
        // But JSON response often displayed in browser. Let's keep it simple JSON response unless needed.

        return $result->setData(['data' => $data]);
    }
}
