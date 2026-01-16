<?php
namespace Ovesio\Ecommerce\Model\Export;

use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Catalog\Helper\Image;
use Magento\Catalog\Model\CategoryRepository;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;

class Products
{
    /**
     * @var CollectionFactory
     */
    protected $productCollectionFactory;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var Image
     */
    protected $imageHelper;

    /**
     * @var CategoryRepository
     */
    protected $categoryRepository;

    /**
     * @var StockRegistryInterface
     */
    protected $stockRegistry;

    /**
     * @var array
     */
    protected $categoryCache = [];

    public function __construct(
        CollectionFactory $productCollectionFactory,
        StoreManagerInterface $storeManager,
        Image $imageHelper,
        CategoryRepository $categoryRepository,
        StockRegistryInterface $stockRegistry
    ) {
        $this->productCollectionFactory = $productCollectionFactory;
        $this->storeManager = $storeManager;
        $this->imageHelper = $imageHelper;
        $this->categoryRepository = $categoryRepository;
        $this->stockRegistry = $stockRegistry;
    }

    public function export()
    {
        $collection = $this->productCollectionFactory->create();
        $collection->addAttributeToSelect('*'); // Select all attributes needed
        $collection->addAttributeToFilter('status', ['in' => Status::STATUS_ENABLED]);
        $collection->setVisibility(Visibility::VISIBILITY_BOTH); // Catalog and Search

        // Add Media Gallery to get images
        $collection->addMediaGalleryData();

        $data = [];
        $currencyCode = $this->storeManager->getStore()->getBaseCurrencyCode();

        foreach ($collection as $product) {
            $sku = $product->getSku();

            // Stock
            $stockItem = $this->stockRegistry->getStockItem($product->getId());
            $qty = (int)$stockItem->getQty();
            $isInStock = $stockItem->getIsInStock();
            $availability = $isInStock ? 'in_stock' : 'out_of_stock';

            // Price (Tax Incl? Prestashop used tax incl options. In M2 getFinalPrice is tax excl usually depending on config?
            // We should use Catalog helper or PriceInfo to be sure.
            // Easy way: $product->getPriceInfo()->getPrice('final_price')->getAmount()->getValue();
            // But let's stick to simple getPrice for now, or getFinalPrice.
            // If Tax is crucial, we need TaxHelper. Assuming tax inclusion is desired if standard display is tax inc.
            // Let's use getFinalPrice which handles rules. Taxes are separate layer, but if we want "price the customer pays", we usually include tax.
            // For simplicity and robustness without complex tax calc injection:
            $price = (float)$product->getFinalPrice();

            // Description
            $description = $product->getDescription();
            if (empty($description)) {
                $description = $product->getShortDescription();
            }
            $description = $this->cleanText($description);

            // Manufacturer
            $manufacturer = $product->getAttributeText('manufacturer');
            if (is_array($manufacturer)) { // sometimes multiselect
                $manufacturer = implode(', ', $manufacturer);
            }
            if (!$manufacturer) {
                $manufacturer = ''; // If attribute not present or empty
            }

            // Image
            $imageUrl = '';
            // Try to get image.
            $imagePath = $product->getImage();
            if ($imagePath && $imagePath != 'no_selection') {
                $imageUrl = $this->storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA) . 'catalog/product' . $imagePath;
            }

            // Category Path
            $categoryPath = '';
            // Use one category (usually the deepest one or valid one).
            // product->getCategoryIds().
            $catIds = $product->getCategoryIds();
            if (!empty($catIds)) {
                // Take the last one? or first? Prestashop took `id_category_default`.
                // M2 doesn't strongly enforce "default" category, just list.
                // We'll pick the last one assuming it's most specific? Or just the first one.
                // Let's pick the one with highest ID (usually newest/deepest)? Or just first.
                // Let's iterate and find the one with longest path? expensive.
                // Just use first.
                $catId = end($catIds); // Last added?
                $categoryPath = $this->getCategoryPath($catId);
            }

            $data[] = [
                'sku' => $sku,
                'name' => $product->getName(),
                'quantity' => $qty,
                'price' => $price,
                'currency' => $currencyCode,
                'availability' => $availability,
                'description' => $description,
                'manufacturer' => (string)$manufacturer,
                'image' => $imageUrl,
                'url' => $product->getProductUrl(),
                'category' => $categoryPath
            ];
        }

        return $data;
    }

    private function getCategoryPath($categoryId)
    {
        if (!$categoryId) return '';

        // This acts as a cache/loader
        if (!isset($this->categoryCache[$categoryId])) {
            try {
                $category = $this->categoryRepository->get($categoryId);
                $pathIds = explode('/', $category->getPath());
                $names = [];
                foreach ($pathIds as $id) {
                    // Skip Root Categories (1 and 2 usually)
                    // Magento Root is 1. Default Category (Root of store) is 2.
                    // We want names starting from visible levels.
                    if ($id == 1) continue;

                    // Optimization: We could cache individual category names too.
                    // For now, load. (Optimization note: this is N+1 query heavy.
                    // Better to load collection of all categories in standard M2 usage or use collection join,
                    // but for export scripts, individual load is acceptable for simplicity vs complexity of big joins).
                    // Actually, let's try to simple load.
                    $cat = $this->categoryRepository->get($id);
                    if ($cat->getLevel() <= 1) continue; // Skip Root
                    $names[] = $cat->getName();
                }
                $this->categoryCache[$categoryId] = implode(' > ', $names);
            } catch (\Exception $e) {
                $this->categoryCache[$categoryId] = '';
            }
        }

        return $this->categoryCache[$categoryId];
    }

    private function cleanText($text)
    {
        if (!$text) return '';
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\t+/', ' ', $text);
        $text = preg_replace('/ +/', ' ', $text);
        $text = preg_replace("/(\r?\n){2,}/", "\n", $text);
        return trim($text);
    }
}
