<?php

namespace Okay\Modules\Sviat\NovaPoshta\Waybills;

use Okay\Modules\Sviat\NovaPoshta\Quote\Parcel;

/** Body of the new API's POST /shipments for a point waybill. */
final class NewShipmentRequest
{
    public static function build(WaybillDraft $draft, SenderParty $sender): array
    {
        $side = new Parcel($draft->weightKg, $draft->volumeM3, $draft->declaredCost)->cubeSideMm();

        return [
            'payerType' => $draft->payerType,
            'sender' => ['countryCode' => 'UA', 'divisionId' => $sender->divisionId, 'name' => $sender->contactName, 'phone' => $sender->phone],
            'recipient' => ['countryCode' => 'UA', 'divisionId' => (int)$draft->divisionId, 'name' => $draft->recipientName, 'phone' => (string)$draft->recipientPhone],
            'parcels' => [[
                'rowNumber' => 1,
                'cargoCategory' => strtolower($draft->cargoType),
                'parcelDescription' => $draft->description,
                'insuranceCost' => $draft->declaredCost,
                // Goods give a volume only: a cube of it, as in the price calculation.
                'length' => $side,
                'width' => $side,
                'height' => $side,
                'actualWeight' => (int)round($draft->weightKg * 1000),
            ]],
        ];
    }
}
