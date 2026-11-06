<?php

namespace Modules\Sviat\NovaPoshta\Dictionaries;

use Okay\Modules\Sviat\NovaPoshta\Dictionaries\Rows\DivisionRow;
use Okay\Modules\Sviat\NovaPoshta\Dictionaries\Rows\NameNormalizer;
use Okay\Modules\Sviat\NovaPoshta\Dictionaries\Rows\SettlementRow;
use Okay\Modules\Sviat\NovaPoshta\Entities\DivisionEntity;
use Okay\Modules\Sviat\NovaPoshta\Entities\SettlementEntity;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class RowsTest extends TestCase
{
    /** Shape of a CDN division record (05.11.2026); personal fields carry invented values. */
    private static function division(array $override = []): array
    {
        return $override + [
            'id' => 4435, 'name' => 'Пункт приймання-видачі (до 30 кг): вул. Старокиївська, 102', 'shortName' => 'Пункт №1477/1',
            'settlement' => ['id' => 126646, 'name' => 'село Перемога'], 'region' => ['id' => 656, 'name' => 'Баришівський район'],
            'parent' => ['id' => 406, 'name' => 'Київська область'], 'address' => 'Перемога, Старокиївська, 102',
            'addressParts' => ['postCode' => '07510', 'building' => '102', 'street' => 'Старокиївська'],
            'number' => '1477/1', 'status' => 'Working', 'divisionCategory' => 'PUDO',
            'publicPhones' => ['0800500609'], 'internalPhones' => ['0670000000'], 'responsiblePerson' => 'Вигадана Особа',
            'externalId' => '230b68fb-981a-11e9-898c-005056b24375', 'countryCode' => 'UA', 'latitude' => 50.4903124, 'longitude' => 31.2292261,
            'maxWeightPlaceRecipient' => 30, 'maxLengthPlaceRecipient' => 120, 'maxWidthPlaceRecipient' => 120, 'maxHeightPlaceRecipient' => 120,
            'maxDeclaredCostPlace' => 29000, 'prohibitedSending' => false, 'prohibitedIssuance' => false,
            'workSchedule' => [['day' => 'monday', 'from' => '08:00', 'to' => '20:00', 'breakFrom' => '', 'breakTo' => '']],
            'photos' => [['url' => 'https://example.test/p.jpg']], 'settings' => [['name' => 'additionalId', 'value' => 'x']],
            'deletedAt' => null,
        ];
    }

    private static function settlement(array $override = []): array
    {
        return $override + [
            'id' => 118064, 'name' => 'місто Київ', 'country' => ['code' => 'UA', 'name' => 'Україна'],
            'region' => ['id' => 406, 'name' => 'Київська область', 'parent' => null],
            'latitude' => 50.450418, 'longitude' => 30.523541, 'postCode1' => '01001', 'postCode2' => '04216',
            'alternativeNames' => ['kyiv', 'місто київ', 'misto kiyiv', 'city kyiv'],
            'prohibitedSending' => false, 'prohibitedIssuance' => false,
        ];
    }

    private static function columns(string $entity): array
    {
        return (new \ReflectionProperty($entity, 'fields'))->getValue();
    }

    public function testDivisionRow(): void
    {
        $row = DivisionRow::fromNp(self::division(), ['name' => 'PUDO (up to 30 kg)', 'shortName' => 'Point 1477/1', 'address' => 'Peremoha, Starokyivska, 102']);

        self::assertSame(4435, $row['id']);
        self::assertSame(126646, $row['settlement_id']);
        self::assertSame('1477/1', $row['number']);
        self::assertSame('PUDO', $row['category']);
        self::assertSame('Working', $row['status']);
        self::assertSame('PUDO (up to 30 kg)', $row['name_en']);
        self::assertSame('Peremoha, Starokyivska, 102', $row['address_en']);
        self::assertSame('07510', $row['post_code']);
        self::assertSame(30.0, $row['max_weight']);
        self::assertSame([120, 120, 120], [$row['max_length'], $row['max_width'], $row['max_height']]);
        self::assertSame(29000.0, $row['max_declared_cost']);
        self::assertSame(0, $row['prohibited_issuance']);
        self::assertSame('monday', json_decode($row['schedule'], true)[0]['day']);
        self::assertSame(40, strlen($row['row_hash']));
    }

    public function testDivisionRowWithoutEnglish(): void
    {
        $row = DivisionRow::fromNp(self::division(), null);

        self::assertNull($row['name_en']);
        self::assertNull($row['short_name_en']);
        self::assertNull($row['address_en']);
    }

    public function testRowsKeepNoPersonalData(): void
    {
        $text = json_encode([DivisionRow::fromNp(self::division(), null), SettlementRow::fromNp(self::settlement(), null)], JSON_UNESCAPED_UNICODE);

        self::assertStringNotContainsString('Вигадана Особа', $text);
        self::assertStringNotContainsString('0670000000', $text);
        self::assertStringNotContainsString('example.test', $text);
        self::assertStringNotContainsString('additionalId', $text);
    }

    public function testRowsUseOnlyTableColumns(): void
    {
        self::assertSame([], array_diff(array_keys(DivisionRow::fromNp(self::division(), null)), self::columns(DivisionEntity::class)));
        self::assertSame([], array_diff(array_keys(SettlementRow::fromNp(self::settlement(), null)), self::columns(SettlementEntity::class)));
        self::assertArrayNotHasKey('boost', SettlementRow::fromNp(self::settlement(), null), 'boost is written separately and must survive a reload');
    }

    public function testHashFollowsTheData(): void
    {
        $a = DivisionRow::fromNp(self::division(), null)['row_hash'];

        self::assertSame($a, DivisionRow::fromNp(self::division(), null)['row_hash']);
        self::assertNotSame($a, DivisionRow::fromNp(self::division(['status' => 'NotWorking']), null)['row_hash']);
        self::assertSame($a, DivisionRow::fromNp(self::division(['responsiblePerson' => 'Інша Особа']), null)['row_hash'], 'ignored fields do not count');
    }

    public function testValuesAreFittedToTheColumns(): void
    {
        $row = DivisionRow::fromNp(self::division([
            'name' => str_repeat('ї', 300), 'number' => str_repeat('9', 40),
            'maxWeightPlaceRecipient' => 1e12, 'maxLengthPlaceRecipient' => 1e12, 'maxDeclaredCostPlace' => 1e15,
        ]), null);

        self::assertSame(255, mb_strlen($row['name_uk']));
        self::assertSame(32, mb_strlen($row['number']));
        self::assertNull($row['max_weight'], 'an impossible limit is unknown, not an error');
        self::assertNull($row['max_length']);
        self::assertNull($row['max_declared_cost']);
        self::assertSame(10, strlen((string)SettlementRow::fromNp(self::settlement(['postCode1' => str_repeat('1', 20)]), null)['post_code']));
    }

    public function testCityOfRegionalSignificance(): void
    {
        $row = SettlementRow::fromNp(self::settlement(), ['name' => 'Kyiv', 'region' => ['id' => 406, 'name' => 'Kyiv region', 'parent' => null], 'alternativeNames' => []]);

        self::assertSame('місто Київ', $row['name_uk']);
        self::assertSame('Kyiv', $row['name_en']);
        self::assertNull($row['district_uk']);
        self::assertSame('Київська область', $row['area_uk']);
        self::assertSame('Kyiv region', $row['area_en']);
        self::assertSame('01001', $row['post_code']);
        self::assertStringContainsString('київ', $row['search_text']);
        self::assertStringContainsString('kyiv', $row['search_text']);
    }

    public function testVillageWithDistrict(): void
    {
        $row = SettlementRow::fromNp(self::settlement([
            'id' => 126646, 'name' => 'село Перемога',
            'region' => ['id' => 656, 'name' => 'Баришівський район', 'parent' => ['id' => 406, 'name' => 'Київська область']],
            'alternativeNames' => [],
        ]), null);

        self::assertSame('Баришівський район', $row['district_uk']);
        self::assertSame('Київська область', $row['area_uk']);
        self::assertNull($row['district_en']);
    }

    public static function names(): array
    {
        return [
            ['місто Кам’янське', 'кам\'янське'],
            ['Кам\'янське', 'кам\'янське'],
            ['село  Кам`янка', 'кам\'янка'],
            ['селище міського типу Ворзель', 'ворзель'],
            ['смт Ворзель', 'ворзель'],
            ['  Біла   Церква ', 'біла церква'],
            ['Пётр', 'петр'],
        ];
    }

    #[DataProvider('names')]
    public function testNormalize(string $raw, string $expected): void
    {
        self::assertSame($expected, NameNormalizer::normalize($raw));
    }

    public function testSearchTextHasNoDuplicates(): void
    {
        self::assertSame('київ kyiv', NameNormalizer::searchText('місто Київ', ['Київ', 'kyiv', 'KYIV', '']));
    }
}
