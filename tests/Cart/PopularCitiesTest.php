<?php

namespace Modules\Sviat\NovaPoshta\Cart;

require_once __DIR__ . '/../Support/InMemoryState.php';

use Modules\Sviat\NovaPoshta\Support\InMemoryState;
use Okay\Modules\Sviat\NovaPoshta\Cart\PopularCities;
use PHPUnit\Framework\TestCase;

class PopularCitiesTest extends TestCase
{
    public function testCleanKeepsOrderDropsDuplicatesAndUnknown(): void
    {
        self::assertSame([3, 1, 7], PopularCities::clean(['3', 1, '3', 'x', -5, 99, '7'], [1, 3, 7]));
    }

    public function testCleanCapsTheList(): void
    {
        $ids = range(1, 30);

        self::assertCount(PopularCities::MAX, PopularCities::clean($ids, $ids));
    }

    public function testStoredIdsRoundTrip(): void
    {
        $state = new InMemoryState();
        PopularCities::store($state, [5, 2]);

        self::assertSame([5, 2], PopularCities::stored($state));
        self::assertSame([], PopularCities::stored(new InMemoryState()));
    }

    public function testBrokenStateIsEmpty(): void
    {
        $state = new InMemoryState();
        $state->set('popular_cities', '{"not":"a list"}');

        self::assertSame([], PopularCities::stored($state));
    }

    public function testButtonLabelDropsTheSettlementType(): void
    {
        self::assertSame('Київ', PopularCities::label('місто Київ'));
        self::assertSame('Ворзель', PopularCities::label('селище міського типу Ворзель'));
        self::assertSame('Kyiv', PopularCities::label('city Kyiv'));
        self::assertSame('Перемога', PopularCities::label('село Перемога'));
        self::assertSame('Біла Церква', PopularCities::label('Біла Церква'));
    }

    /** The button stays short; the chosen-city line shows the type, as for a city found by search. */
    public function testButtonCarriesTheType(): void
    {
        self::assertSame(['id' => 7, 'label' => 'Київ', 'type' => 'місто'], PopularCities::button(['id' => 7, 'name' => 'місто Київ']));
        self::assertSame(['id' => 8, 'label' => 'Ворзель', 'type' => 'смт'], PopularCities::button(['id' => 8, 'name' => 'селище міського типу Ворзель']));
    }
}
