<?php

namespace Okay\Modules\Sviat\NovaPoshta\Extenders;

use Okay\Core\Modules\Extender\ExtensionInterface;
use Okay\Modules\Sviat\NovaPoshta\Products\VariantVolume;

class BackendExtender implements ExtensionInterface
{
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
