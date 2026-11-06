<?php

namespace Okay\Modules\Sviat\NovaPoshta\Extenders;

use Okay\Core\Design;
use Okay\Core\Modules\Extender\ExtensionInterface;
use Okay\Modules\Sviat\NovaPoshta\Orders\OrderDeliveryStore;
use Okay\Modules\Sviat\NovaPoshta\Orders\OrderDeliveryView;
use Okay\Modules\Sviat\NovaPoshta\Products\VariantVolume;

class BackendExtender implements ExtensionInterface
{
    public function __construct(
        private readonly Design $design,
        private readonly OrderDeliveryStore $orders,
    ) {
    }

    /** QueueExtension of BackendOrdersHelper::findOrder: the order page shows where the parcel goes. */
    public function assignOrderDelivery($order, $orderId = null)
    {
        $row = is_object($order) && !empty($order->id) ? $this->orders->forOrder((int)$order->id) : null;
        $this->design->assign('np_order_delivery', $row ? OrderDeliveryView::lines($row) : null);
    }

    /** ChainExtension of BackendProductsRequest::postVariants: the volume column is decimal, text must not reach it. */
    public function cleanVariantVolumes($variants)
    {
        if (!is_array($variants)) {
            return $variants;
        }
        foreach ($variants as $variant) {
            if (is_object($variant) && property_exists($variant, VariantVolume::FIELD)) {
                $variant->{VariantVolume::FIELD} = VariantVolume::clean($variant->{VariantVolume::FIELD});
            }
        }

        return $variants;
    }
}
