<?php

namespace Okay\Modules\Sviat\NovaPoshta\Legacy;

/**
 * Що лишили по собі OkayCMS/NovaposhtaCost, Sviat/NovaPoshtaTracking і Sviat/NovaPoshtaPopularCities.
 * Лише читання: їхні таблиці лишаються як є. Видалений модуль просто нічого не дає.
 */
interface LegacyData
{
    public const string COST = 'OkayCMS/NovaposhtaCost';
    public const string TRACKING = 'Sviat/NovaPoshtaTracking';
    public const string POPULAR = 'Sviat/NovaPoshtaPopularCities';

    /**
     * Рядки okaycms__np_cost_delivery_data після заданого id, за зростанням; колонки Tracking — null, коли їх немає.
     * @return list<object>
     */
    public function deliveryRows(int $afterId, int $limit): array;

    /** @return list<object> рядки sviat__novaposhta_tracking після заданого id, за зростанням */
    public function trackingRows(int $afterId, int $limit): array;

    /** @return object|null ref, city_ref, number (?int), name (українською) старого відділення */
    public function warehouse(string $ref): ?object;

    public function cityName(string $cityRef): ?string;

    /** @return list<int> способи оплати, які NovaposhtaCost позначив як післяплату */
    public function codPaymentIds(): array;

    /** @return list<object> id, volume варіантів після заданого id зі старим об'ємом > 0 */
    public function volumes(int $afterId, int $limit): array;

    public function setting(string $name): ?string;

    /** @return array{orders:int, waybills:int, payments:int, volumes:int} що переглянув би запуск */
    public function counts(): array;

    /** @return list<string> vendor/name старих модулів, які встановлені й увімкнені */
    public function enabledModules(): array;

    /** @return list<object> id, name, service_type способів доставки, досі прив'язаних до NovaposhtaCost */
    public function legacyDeliveries(): array;
}
