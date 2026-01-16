<?php
namespace Ovesio\Ecommerce\Block\Adminhtml\System\Config;

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;

class Info extends Field
{
    /**
     * @var ScopeConfigInterface
     */
    protected $_scopeConfig;

    /**
     * @var WriterInterface
     */
    protected $_configWriter;

    /**
     * @param Context $context
     * @param ScopeConfigInterface $scopeConfig
     * @param WriterInterface $configWriter
     * @param array $data
     */
    public function __construct(
        Context $context,
        ScopeConfigInterface $scopeConfig,
        WriterInterface $configWriter,
        array $data = []
    ) {
        $this->_scopeConfig = $scopeConfig;
        $this->_configWriter = $configWriter;
        parent::__construct($context, $data);
    }

    /**
     * @param AbstractElement $element
     * @return string
     */
    public function render(AbstractElement $element)
    {
        $hash = $this->_scopeConfig->getValue('ovesio_ecommerce/general/hash');



        $baseUrl = $this->_storeManager->getStore()->getBaseUrl();
        // Fallback to default store if needed, but getBaseUrl usually works for admin too if configured,
        // wait, we want the FRONTEND url.
        // But usually in admin context getBaseUrl returns admin url? No, depends on type.
        // Let's use `_storeManager->getStore(null)->getBaseUrl()` or similar.
        // But safer: just `/ovesio/feed/index`.

        // Actually, we need the frontend URL.
        // Let's rely on `_urlBuilder` but targeting frontend? M2 admin url builder builds admin urls.
        // We can construct it manually or use valid store retrieval.
        // Let's try to get a frontend store.
        $store = $this->getCurrentStore();
        $baseUrl = $store->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_WEB);

        $productFeedUrl = $baseUrl . 'ovesio/feed/index/hash/' . $hash . '/action/products';
        $orderFeedUrl = $baseUrl . 'ovesio/feed/index/hash/' . $hash . '/action/orders';

        $html = '<div style="padding:10px;background-color:#f8f9fa;border:1px solid #ddd;margin-bottom:10px;">';
        $html .= '<h3>' . __('Ovesio - Ecommerce Intelligence') . '</h3>';
        $html .= '<p>' . __('Empowers your store with advanced AI-driven insights.') . '</p>';
        $html .= '<p><strong>' . __('Product Feed URL:') . '</strong> <a href="' . $productFeedUrl . '" target="_blank">' . $productFeedUrl . '</a></p>';
        $html .= '<p><strong>' . __('Order Feed URL:') . '</strong> <a href="' . $orderFeedUrl . '" target="_blank">' . $orderFeedUrl . '</a></p>';
        $html .= '<p>' . __('Security Hash: ') . '<code>' . $hash . '</code></p>';
        $html .= '</div>';

        return $html;
    }

    protected function getCurrentStore()
    {
        // For admin, we might want the default store or the one currently selected in scope?
        // Simple approach: default store.
        return $this->_storeManager->getStore();
    }
}
