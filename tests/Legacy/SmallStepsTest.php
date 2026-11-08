<?php

namespace Modules\Sviat\NovaPoshta\Legacy;

require_once __DIR__ . '/../Support/FakeLegacyData.php';
require_once __DIR__ . '/../Support/FakeLegacyPlaces.php';
require_once __DIR__ . '/../Support/FakeLegacyTargets.php';

use Modules\Sviat\NovaPoshta\Support\FakeLegacyData;
use Modules\Sviat\NovaPoshta\Support\FakeLegacyPlaces;
use Modules\Sviat\NovaPoshta\Support\FakeLegacyTargets;
use Okay\Modules\Sviat\NovaPoshta\Api\ApiKey;
use Okay\Modules\Sviat\NovaPoshta\Backend\ShippingSettings;
use Okay\Modules\Sviat\NovaPoshta\Legacy\LegacyBudget;
use Okay\Modules\Sviat\NovaPoshta\Legacy\LegacyCityMatcher;
use Okay\Modules\Sviat\NovaPoshta\Legacy\LegacyDivisionMatcher;
use Okay\Modules\Sviat\NovaPoshta\Legacy\LegacyReport;
use Okay\Modules\Sviat\NovaPoshta\Legacy\LegacySettingsMap;
use Okay\Modules\Sviat\NovaPoshta\Legacy\LegacyStep;
use Okay\Modules\Sviat\NovaPoshta\Legacy\Steps\PaymentsStep;
use Okay\Modules\Sviat\NovaPoshta\Legacy\Steps\SettingsStep;
use Okay\Modules\Sviat\NovaPoshta\Legacy\Steps\VolumesStep;
use PHPUnit\Framework\TestCase;

class SmallStepsTest extends TestCase
{
    private FakeLegacyData $old;
    private FakeLegacyTargets $ours;
    private FakeLegacyPlaces $places;

    protected function setUp(): void
    {
        $this->old = new FakeLegacyData();
        $this->ours = new FakeLegacyTargets();
        $this->places = new FakeLegacyPlaces();
        $this->places->settlements = [
            (object)['id' => 1, 'name_uk' => 'місто Київ', 'area_uk' => 'Київська область', 'district_uk' => null, 'has_pickup' => 1],
            (object)['id' => 2, 'name_uk' => 'місто Олександрія', 'area_uk' => 'Кіровоградська область', 'district_uk' => null, 'has_pickup' => 1],
            (object)['id' => 3, 'name_uk' => 'село Олександрія', 'area_uk' => 'Рівненська область', 'district_uk' => null, 'has_pickup' => 1],
        ];
        $this->places->divisions = [(object)['id' => 10, 'settlement_id' => 1, 'number' => '11/13', 'category' => 'Branch', 'name_uk' => 'Відділення №13: вул. Дорогожицька, 4', 'address_uk' => null]];
        $this->old->cities = ['kyiv' => 'Київ'];
        $this->old->warehouses['w13'] = (object)['ref' => 'w13', 'city_ref' => 'kyiv', 'number' => 13, 'name' => 'Відділення №13: вул. Дорогожицька, 4'];
    }

    private function budget(): LegacyBudget
    {
        return new LegacyBudget(PHP_INT_MAX, 0);
    }

    public function testControlKindFromTracking(): void
    {
        $this->old->cod = [18, 19];
        $this->old->settings['novapost_payment_control'] = '1';
        $this->ours->payments = [18 => (object)['id' => 18, 'module' => 'null', 'cod' => 0], 19 => (object)['id' => 19, 'module' => 'null', 'cod' => 2]];
        $report = new LegacyReport();

        self::assertSame(LegacyStep::DONE, new PaymentsStep($this->old, $this->ours)->run(0, $this->budget(), $report));
        self::assertSame(3, $this->ours->payments[18]->cod, 'payment control');
        self::assertSame(2, $this->ours->payments[19]->cod, 'the manager already chose');
        self::assertSame(['done' => 1, 'existed' => 1, 'failed' => 0], $report->counts('payments'));

        unset($this->old->settings['novapost_payment_control']);
        $this->ours->payments[18]->cod = 0;
        new PaymentsStep($this->old, $this->ours)->run(0, $this->budget(), new LegacyReport());
        self::assertSame(2, $this->ours->payments[18]->cod, 'money transfer without the Tracking switch');
    }

    public function testOnlinePaymentNotMarked(): void
    {
        $this->old->cod = [6];
        $this->ours->payments = [6 => (object)['id' => 6, 'module' => 'OkayCMS/LiqPay', 'cod' => 0]];
        $report = new LegacyReport();
        new PaymentsStep($this->old, $this->ours)->run(0, $this->budget(), $report);

        self::assertSame(0, $this->ours->payments[6]->cod);
        self::assertSame('legacy_fail_online_payment', $report->fails()[0]['key']);
    }

    public function testExistingVolumeKept(): void
    {
        $this->old->variantVolumes = [(object)['id' => 1, 'volume' => '0.002'], (object)['id' => 2, 'volume' => '0.5']];
        $this->ours->volumes = [1 => 0.0, 2 => 0.01];
        $report = new LegacyReport();
        new VolumesStep($this->old, $this->ours)->run(0, $this->budget(), $report);

        self::assertSame(0.002, $this->ours->volumes[1]);
        self::assertSame(0.01, $this->ours->volumes[2]);
        self::assertSame(['done' => 1, 'existed' => 1, 'failed' => 0], $report->counts('volumes'));
    }

    public function testSettingsFillOnlyEmpty(): void
    {
        $this->old->settings = ['newpost_key' => 'old-key', 'newpost_weight' => '1,5', 'novapost_payer_type' => 'Sender', 'novapost_cargo_type' => 'Cargo', 'novapost_sender_warehouse' => 'w13', 'novapost_sender_phone' => '+38 (050) 000-00-00'];
        $this->ours->setOurSetting(ApiKey::SETTING, 'new-key');
        $report = new LegacyReport();
        $step = new SettingsStep($this->old, $this->ours, new LegacyCityMatcher($this->places), new LegacyDivisionMatcher($this->places));

        self::assertSame(LegacyStep::DONE, $step->run(0, $this->budget(), $report));
        self::assertSame('new-key', $this->ours->settings[ApiKey::SETTING]);
        self::assertSame('1.5', $this->ours->settings[ShippingSettings::WEIGHT]);
        // The old module had no «as in the order»: its explicit payer would switch off the safe default.
        self::assertArrayNotHasKey('sviat__novaposhta__waybill_payer_type', $this->ours->settings);
        self::assertSame('Cargo', $this->ours->settings['sviat__novaposhta__waybill_cargo_type']);
        self::assertSame('10', $this->ours->settings[ShippingSettings::SENDER]);
        self::assertSame('380500000000', $this->ours->settings['sviat__novaposhta__waybill_sender_phone']);
        self::assertSame(['done' => 4, 'existed' => 1, 'failed' => 0], $report->counts('settings'));
    }

    public function testInvalidCutoffSkipped(): void
    {
        $result = LegacySettingsMap::values(static fn(string $n): ?string => ['novapost_time_today_date' => '25:00', 'novapost_payment_method' => 'Card', 'newpost_volume' => '0.001'][$n] ?? null, static fn(string $n): ?string => null);

        self::assertSame([ShippingSettings::VOLUME => '0.001'], $result['set']);
        self::assertSame(['sviat__novaposhta__waybill_payment_method', 'sviat__novaposhta__waybill_cutoff'], $result['skipped']);
    }
}
