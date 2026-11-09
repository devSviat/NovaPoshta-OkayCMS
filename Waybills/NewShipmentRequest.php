<?php

namespace Okay\Modules\Sviat\NovaPoshta\Waybills;

use Okay\Modules\Sviat\NovaPoshta\Quote\Parcel;

/** Body of the new API's POST /shipments for a point waybill. */
final class NewShipmentRequest
{
    /**
     * Documents go as the largest envelope the API docs allow (35 × 25 × 2 cm, up to 1 kg): the API takes any size
     * and records its volume weight, so a cube of the goods' volume would weigh the documents as a parcel.
     */
    private const array DOCUMENTS_MM = [350, 250, 20];
    private const int DOCUMENTS_MAX_G = 1000;

    public static function build(WaybillDraft $draft, SenderParty $sender): array
    {
        $documents = $draft->cargoType === 'Documents';
        $side = new Parcel($draft->weightKg, $draft->volumeM3, $draft->declaredCost)->cubeSideMm();
        [$length, $width, $height] = $documents ? self::DOCUMENTS_MM : [$side, $side, $side];
        $grams = (int)round($draft->weightKg * 1000);

        $body = [
            // Lets the module find the shipment by its order when NP did not answer the create.
            'clientOrder' => (string)$draft->orderId,
            'payerType' => $draft->payerType,
            'sender' => ['countryCode' => 'UA', 'divisionId' => $sender->divisionId, 'name' => $sender->contactName, 'phone' => $sender->phone],
            'recipient' => ['countryCode' => 'UA', 'divisionId' => (int)$draft->divisionId, 'name' => $draft->recipientName, 'phone' => (string)$draft->recipientPhone],
            'parcels' => [[
                'rowNumber' => 1,
                'cargoCategory' => strtolower($draft->cargoType),
                'parcelDescription' => $draft->description,
                'insuranceCost' => $draft->declaredCost,
                // Goods give a volume only: a cube of it, as in the price calculation.
                'length' => $length,
                'width' => $width,
                'height' => $height,
                'actualWeight' => $documents ? min($grams, self::DOCUMENTS_MAX_G) : $grams,
            ]],
        ];

        return $draft->extraInfo !== '' ? $body + ['note' => $draft->extraInfo] : $body;
    }
}
