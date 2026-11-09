<?php

namespace Okay\Modules\Sviat\NovaPoshta\Cart;

use Okay\Core\EntityFactory;
use Okay\Modules\Sviat\NovaPoshta\Entities\SettlementEntity;
use Okay\Modules\Sviat\NovaPoshta\Storage\StateStorage;

/** City buttons above the cart's city field: chosen and ordered by the manager only (design §6). */
final class PopularCities
{
    public const int MAX = 20;
    private const string STATE = 'popular_cities';
    private const string TYPE_PREFIX = '/^(?:селище міського типу|місто|село|селище|смт|urban-type settlement|city|village|town|settlement)\s+/iu';

    public function __construct(
        private readonly StateStorage $state,
        private readonly EntityFactory $entityFactory,
    ) {
    }

    /** @param list<int|string> $ids in the order the manager put them */
    public function save(array $ids): void
    {
        $wanted = array_values(array_filter(array_map('intval', $ids), static fn(int $id): bool => $id > 0));
        $existing = $wanted === [] ? [] : array_map('intval', array_keys(
            $this->entityFactory->get(SettlementEntity::class)->mappedBy('id')->cols(['id'])->noLimit()->find(['id' => $wanted])
        ));
        self::store($this->state, self::clean($ids, $existing));
    }

    /** @return list<array{id:int, name:string, area:?string}> */
    public function list(string $lang): array
    {
        $ids = self::stored($this->state);
        if ($ids === []) {
            return [];
        }
        $rows = $this->entityFactory->get(SettlementEntity::class)->mappedBy('id')
            ->cols(['id', 'name_uk', 'name_en', 'area_uk', 'area_en'])->noLimit()->find(['id' => $ids]);
        $english = $lang === 'en';
        $list = [];
        foreach ($ids as $id) {
            if (isset($rows[$id])) {
                $row = $rows[$id];
                $list[] = [
                    'id' => $id,
                    'name' => (string)($english && $row->name_en ? $row->name_en : $row->name_uk),
                    'title' => self::label((string)($english && $row->name_en ? $row->name_en : $row->name_uk)),
                    'area' => ($english && $row->area_en ? $row->area_en : $row->area_uk) ?: null,
                ];
            }
        }

        return $list;
    }

    /** @return list<array{id:int, label:string}> buttons for the cart */
    public function buttons(string $lang): array
    {
        return array_map(static fn(array $city): array => ['id' => $city['id'], 'label' => self::label($city['name'])], $this->list($lang));
    }

    /**
     * @param list<mixed> $raw
     * @param list<int> $existingIds
     * @return list<int>
     */
    public static function clean(array $raw, array $existingIds): array
    {
        $clean = [];
        foreach ($raw as $id) {
            $id = is_numeric($id) ? (int)$id : 0;
            if ($id > 0 && in_array($id, $existingIds, true) && !in_array($id, $clean, true)) {
                $clean[] = $id;
            }
        }

        return array_slice($clean, 0, self::MAX);
    }

    /** @return list<int> */
    public static function stored(StateStorage $state): array
    {
        $ids = json_decode((string)$state->get(self::STATE), true);

        return is_array($ids) && array_is_list($ids) ? array_values(array_filter(array_map('intval', $ids))) : [];
    }

    /** @param list<int> $ids */
    public static function store(StateStorage $state, array $ids): void
    {
        $state->set(self::STATE, json_encode(array_values($ids)));
    }

    /** "місто Київ" → "Київ": a button is short, and namesakes are not offered as buttons anyway. */
    public static function label(string $name): string
    {
        $title = SettlementName::split($name)[1];

        return preg_replace(self::TYPE_PREFIX, '', $title) ?? $title;
    }
}
