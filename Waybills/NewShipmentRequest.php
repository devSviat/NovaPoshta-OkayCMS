<?php

namespace Okay\Modules\Sviat\NovaPoshta\Waybills;

use Okay\Modules\Sviat\NovaPoshta\Quote\Parcel;

/** Тіло POST /shipments нового API для накладної на пункт видачі. */
final class NewShipmentRequest
{
    /**
     * Документи йдуть як найбільший конверт, який дозволяє документація API (35 × 25 × 2 см, до 1 кг): API приймає
     * будь-який розмір і записує його об'ємну вагу, тож куб з об'єму товарів зважив би документи як посилку.
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
            // Дає модулю знайти відправлення за замовленням, коли НП не відповіла на створення.
            'clientOrder' => (string)$draft->orderId,
            'payerType' => $draft->payerType,
            'sender' => ['countryCode' => 'UA', 'divisionId' => $sender->divisionId, 'name' => $sender->contactName, 'phone' => $sender->phone],
            'recipient' => ['countryCode' => 'UA', 'divisionId' => (int)$draft->divisionId, 'name' => $draft->recipientName, 'phone' => (string)$draft->recipientPhone],
            'parcels' => [[
                'rowNumber' => 1,
                'cargoCategory' => strtolower($draft->cargoType),
                'parcelDescription' => $draft->description,
                'insuranceCost' => $draft->declaredCost,
                // Товари дають лише об'єм: куб з нього, як у розрахунку ціни.
                'length' => $length,
                'width' => $width,
                'height' => $height,
                'actualWeight' => $documents ? min($grams, self::DOCUMENTS_MAX_G) : $grams,
            ]],
        ];

        return $draft->extraInfo !== '' ? $body + ['note' => $draft->extraInfo] : $body;
    }
}
