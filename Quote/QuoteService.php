<?php

namespace Okay\Modules\Sviat\NovaPoshta\Quote;

use Okay\Modules\Sviat\NovaPoshta\Api\NewApi\NewApiClient;
use Okay\Modules\Sviat\NovaPoshta\Api\NpApiException;
use Okay\Modules\Sviat\NovaPoshta\Api\NpErrorKind;
use Okay\Modules\Sviat\NovaPoshta\Api\OldApi\OldApiClient;

/**
 * Delivery price and term for the cart (design §4, plan 3b). The new calculator prices branch to
 * branch without services; the old one is needed when the customer pays a COD commission or the
 * shop hands parcels to a courier. The term only comes from the old API: the new one returns
 * no date (checked live 06.11.2026).
 */
final class QuoteService
{
    private const int TIMEOUT_SECONDS = 5;

    private readonly \Closure $now;

    public function __construct(
        private readonly NewApiClient $newApi,
        private readonly OldApiClient $oldApi,
        private readonly CityRefResolver $cityRefs,
        ?\Closure $now = null,
    ) {
        $this->now = $now ?? static fn(): int => time();
    }

    public function quote(QuoteRequest $request): DeliveryQuote
    {
        $price = null;
        $codFee = null;
        $term = null;
        $problem = null;
        $source = 'none';

        if ($request->wantPrice) {
            $useNew = str_starts_with($request->serviceType, 'Warehouse') && (!$request->cod || $request->codKind === CodKind::Control);
            try {
                [$price, $codFee] = $useNew ? [$this->newPrice($request), null] : $this->oldPrice($request);
                $source = $useNew ? 'new' : 'old';
            } catch (QuoteProblem $e) {
                $problem = $e->getMessage();
                $source = 'fallback';
            }
        }

        if ($request->wantTerm) {
            try {
                $term = $this->term($request);
            } catch (QuoteProblem $e) {
                $problem ??= $e->getMessage();
            }
        }

        return new DeliveryQuote($price, $term, $source, $problem, $codFee);
    }

    private function newPrice(QuoteRequest $request): float
    {
        $side = $request->parcel->cubeSideMm();
        $answer = $this->guard(fn(): array => $this->newApi->quick(self::TIMEOUT_SECONDS)->post('/shipments/calculations', [
            'payerType' => 'Recipient',
            'parcels' => [[
                'cargoCategory' => 'parcel',
                'insuranceCost' => $request->parcel->declaredCost,
                'rowNumber' => 1,
                'width' => $side,
                'length' => $side,
                'height' => $side,
                'actualWeight' => (int)round($request->parcel->weightKg * 1000),
            ]],
            'sender' => ['countryCode' => 'UA', 'divisionId' => (int)$request->sender->id],
            'recipient' => ['countryCode' => 'UA', 'divisionId' => (int)$request->recipient->id],
        ]));
        $costs = array_column((array)($answer['services'] ?? []), 'cost');
        if ($costs === [] || array_filter($costs, static fn($c): bool => !is_numeric($c)) !== []) {
            throw new QuoteProblem('rejected');
        }

        return round((float)array_sum($costs), 2);
    }

    /** @return array{0: float, 1: ?float} price and the COD commission inside it */
    private function oldPrice(QuoteRequest $request): array
    {
        $properties = $this->cities($request) + [
            'Weight' => self::number($request->parcel->weightKg, 3),
            'VolumeGeneral' => self::number($request->parcel->volumeM3, 5),
            'Cost' => self::number($request->parcel->declaredCost, 2),
            'CargoType' => 'Parcel',
            'SeatsAmount' => '1',
            'ServiceType' => $request->serviceType,
        ];
        if ($request->cod && $request->codKind === CodKind::Money) {
            $properties['RedeliveryCalculate'] = ['CargoType' => 'Money', 'Amount' => self::number($request->parcel->declaredCost, 2)];
        }
        $row = $this->guard(fn(): array => $this->oldApi->quick(self::TIMEOUT_SECONDS)->call('InternetDocument', 'getDocumentPrice', $properties))[0] ?? null;
        if (!is_array($row) || !is_numeric($row['Cost'] ?? null)) {
            throw new QuoteProblem('rejected');
        }

        $fee = is_numeric($row['CostRedelivery'] ?? null) ? round((float)$row['CostRedelivery'], 2) : null;

        return [round((float)$row['Cost'] + ($fee ?? 0.0), 2), $fee];
    }

    private function term(QuoteRequest $request): ?\DateTimeImmutable
    {
        $row = $this->guard(fn(): array => $this->oldApi->quick(self::TIMEOUT_SECONDS)->call('InternetDocument', 'getDocumentDeliveryDate', $this->cities($request) + [
            'ServiceType' => $request->serviceType,
            'DateTime' => date('d.m.Y', ($this->now)()),
        ]))[0] ?? null;
        $date = is_array($row) ? (string)($row['DeliveryDate']['date'] ?? '') : '';

        return preg_match('/^\d{4}-\d{2}-\d{2}/', $date) ? new \DateTimeImmutable(substr($date, 0, 10)) : throw new QuoteProblem('rejected');
    }

    /** @return array{CitySender: string, CityRecipient: string} */
    private function cities(QuoteRequest $request): array
    {
        $sender = $this->cityRefs->forSettlement((int)$request->sender->settlement_id);
        $recipient = $this->cityRefs->forSettlement((int)$request->recipient->settlement_id);
        if ($sender === null || $recipient === null) {
            throw new QuoteProblem('no_city_ref');
        }

        return ['CitySender' => $sender, 'CityRecipient' => $recipient];
    }

    private function guard(callable $call): array
    {
        try {
            return $call();
        } catch (NpApiException $e) {
            throw new QuoteProblem(in_array($e->kind, [NpErrorKind::Unavailable, NpErrorKind::RateLimited], true) ? 'unreachable' : 'rejected');
        }
    }

    private static function number(float $value, int $decimals): string
    {
        $text = rtrim(rtrim(number_format($value, $decimals, '.', ''), '0'), '.');

        return $text === '' ? '0' : $text;
    }
}
