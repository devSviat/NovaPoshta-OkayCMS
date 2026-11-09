<?php

namespace Okay\Modules\Sviat\NovaPoshta\Legacy;

/**
 * What OkayCMS/NovaposhtaCost, Sviat/NovaPoshtaTracking and Sviat/NovaPoshtaPopularCities left behind.
 * Read only: their tables stay as they are. A removed module simply gives nothing.
 */
interface LegacyData
{
    public const string COST = 'OkayCMS/NovaposhtaCost';
    public const string TRACKING = 'Sviat/NovaPoshtaTracking';
    public const string POPULAR = 'Sviat/NovaPoshtaPopularCities';

    /**
     * Rows of okaycms__np_cost_delivery_data after the given id, ascending; Tracking's columns are null when absent.
     * @return list<object>
     */
    public function deliveryRows(int $afterId, int $limit): array;

    /** @return list<object> rows of sviat__novaposhta_tracking after the given id, ascending */
    public function trackingRows(int $afterId, int $limit): array;

    /** @return object|null ref, city_ref, number (?int), name (Ukrainian) of an old warehouse */
    public function warehouse(string $ref): ?object;

    public function cityName(string $cityRef): ?string;

    /** @return list<int> payment methods NovaposhtaCost marked as cash on delivery */
    public function codPaymentIds(): array;

    /** @return list<object> id, volume of variants after the given id with an old volume > 0 */
    public function volumes(int $afterId, int $limit): array;

    public function setting(string $name): ?string;

    /** @return array{orders:int, waybills:int, payments:int, volumes:int} what a run would look at */
    public function counts(): array;

    /** @return list<string> vendor/name of the old modules that are installed and enabled */
    public function enabledModules(): array;

    /** @return list<object> id, name, service_type of delivery methods still bound to NovaposhtaCost */
    public function legacyDeliveries(): array;
}
