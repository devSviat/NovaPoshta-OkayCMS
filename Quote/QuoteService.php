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
        $liftingFee = null;
        $liftingRefused = false;
        $term = null;
        $problem = null;
        $source = 'none';

        if ($request->wantPrice) {
            $useNew = $request->address === null && str_starts_with($request->serviceType, 'Warehouse') && (!$request->cod || $request->codKind === CodKind::Control);
            try {
                if ($request->address?->offerLifting) {
                    // NP has no "lifting price" method: the extra is the difference of two calculations,
                    // so the weight limit for lifting stays NP's, not ours (design §6).
                    $base = $this->oldPrice($request, false);
                    try {
                        $lifted = $this->oldPrice($request, true);
                    } catch (QuoteProblem $e) {
                        if ($e->getMessage() !== 'rejected' && $request->address->lifting) {
                            throw $e;
                        }
                        // Too heavy to lift, or the second call failed: the delivery is still priced, lifting is not offered.
                        $lifted = null;
                        $liftingRefused = true;
                    }
                    [$price, $codFee] = $request->address->lifting && $lifted !== null ? $lifted : $base;
                    $fee = $lifted === null ? 0.0 : round($lifted[0] - $base[0], 2);
                    $liftingFee = $fee > 0.004 ? $fee : null;
                } else {
                    [$price, $codFee] = $useNew ? [$this->newPrice($request), null] : $this->oldPrice($request, $request->address?->lifting ?? false);
                }
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

        return new DeliveryQuote($price, $term, $source, $problem, $codFee, $liftingFee, $liftingRefused);
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

    /** @return array{0: float, 1: ?float} delivery price and, apart from it, the COD commission */
    private function oldPrice(QuoteRequest $request, bool $lifting): array
    {
        $properties = $this->cities($request) + [
            'Weight' => self::number($request->parcel->weightKg, 3),
            'VolumeGeneral' => self::number($request->parcel->volumeM3, 5),
            'Cost' => self::number($request->parcel->declaredCost, 2),
            'CargoType' => 'Parcel',
            'SeatsAmount' => '1',
            'ServiceType' => $request->serviceType,
        ];
        if ($lifting && $request->address !== null) {
            $properties['NumberOfFloorsLifting'] = (string)($request->address->floor ?? 1);
            $properties['Elevator'] = $request->address->elevator ? '1' : '0';
        }
        if ($request->cod && $request->codKind === CodKind::Money) {
            $properties['RedeliveryCalculate'] = ['CargoType' => 'Money', 'Amount' => self::number($request->parcel->declaredCost, 2)];
        }
        $row = $this->guard(fn(): array => $this->oldApi->quick(self::TIMEOUT_SECONDS)->call('InternetDocument', 'getDocumentPrice', $properties))[0] ?? null;
        if (!is_array($row) || !is_numeric($row['Cost'] ?? null)) {
            throw new QuoteProblem('rejected');
        }

        $fee = is_numeric($row['CostRedelivery'] ?? null) ? round((float)$row['CostRedelivery'], 2) : null;

        return [round((float)$row['Cost'], 2), $fee];
    }

    private function term(QuoteRequest $request): ?\DateTimeImmutable
    {
        $row = $this->guard(fn(): array => $this->oldApi->quick(self::TIMEOUT_SECONDS)->call('InternetDocument', 'getDocumentDeliveryDate', $this->cities($request) + [
            'ServiceType' => $request->serviceType,
            'DateTime' => date('d.m.Y', ($this->now)()),
        ]))[0] ?? null;
        $date = is_array($row) ? (string)($row['DeliveryDate']['date'] ?? '') : '';

        $day = \DateTimeImmutable::createFromFormat('!Y-m-d', substr($date, 0, 10));
        $errors = \DateTimeImmutable::getLastErrors();

        return $day && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0)) ? $day : throw new QuoteProblem('rejected');
    }

    /** @return array{CitySender: string, CityRecipient: string} */
    private function cities(QuoteRequest $request): array
    {
        $sender = $this->cityRef((int)$request->sender->settlement_id);

        return ['CitySender' => $sender, 'CityRecipient' => $request->address?->cityRef ?? $this->cityRef((int)$request->recipient?->settlement_id)];
    }

    private function cityRef(int $settlementId): string
    {
        try {
            return $this->cityRefs->forSettlement($settlementId) ?? throw new QuoteProblem('no_city_ref');
        } catch (CityRefUnavailable) {
            throw new QuoteProblem('unreachable');
        }
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
