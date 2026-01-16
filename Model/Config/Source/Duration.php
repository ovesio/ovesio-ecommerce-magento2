<?php
namespace Ovesio\Ecommerce\Model\Config\Source;

use Magento\Framework\Option\ArrayInterface;

class Duration implements ArrayInterface
{
    /**
     * @return array
     */
    public function toOptionArray()
    {
        return [
            ['value' => 12, 'label' => __('Last 12 Months')],
            ['value' => 24, 'label' => __('Last 24 Months')]
        ];
    }
}
