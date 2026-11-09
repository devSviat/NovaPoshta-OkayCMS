<?php

namespace Okay\Modules\Sviat\NovaPoshta\Cart;

use Okay\Core\Database;
use Okay\Core\QueryFactory;
use Okay\Modules\Sviat\NovaPoshta\Entities\DivisionEntity;
use Okay\Modules\Sviat\NovaPoshta\Groups\GroupsStore;

/**
 * Пункти видачі одного населеного пункту для кошика: вкладки груп із кількістю й одна сторінка
 * обраної групи. Лише в Києві ~7 400 поштоматів, тож до решти доходять через пошук.
 */
final class DivisionList
{
    public const int PAGE = 100;
    private const int MAX_SEARCH = 64;
    private const int MAX_WORDS = 5;
    /** Нова Пошта показує поштомати власних техніків як робочі пункти видачі, позначаючи це лише в назві. */
    private const string TEST_MARK = 'ТЕСТОВИЙ';

    public function __construct(
        private readonly Database $db,
        private readonly QueryFactory $queryFactory,
        private readonly GroupsStore $groups,
    ) {
    }

    /** @return array{groups: list<array{id:int, name:string, count:int}>, group: ?int, divisions: list<array>, total: int, dictionary?: string} */
    public function forSettlement(int $settlementId, CartMetrics $cart, string $lang, ?int $groupId, string $query): array
    {
        $groupRows = $this->groups->all();
        $counts = [];
        foreach ($this->rows($this->base($settlementId)->cols(['d.category', 'COUNT(*) AS n'])->groupBy(['d.category'])) as $row) {
            $counts[$row->category] = (int)$row->n;
        }
        $groups = self::groupCounts($groupRows, $counts);
        $ids = array_column($groups, 'id');
        $chosen = in_array($groupId, $ids, true) ? $groupId : ($ids[0] ?? null);

        $result = ['groups' => $groups, 'group' => $chosen, 'divisions' => [], 'total' => 0];
        if ($chosen === null) {
            if ($this->isDictionaryEmpty()) {
                $result['dictionary'] = 'empty';
            }

            return $result;
        }

        $categories = self::categoriesOf($groupRows, $chosen);
        $words = self::searchWords($query);
        $filtered = function () use ($settlementId, $categories, $words) {
            $select = $this->base($settlementId)->where('d.category IN (:categories)')->bindValue('categories', $categories);
            // Кожне слово має знайтися десь: "Дорогожицька 4" — це вулиця й будинок в одній адресі.
            foreach ($words ?? [] as $i => $word) {
                $select->where("(SUBSTRING_INDEX(d.number, '/', -1) LIKE :wp{$i} OR d.name_uk LIKE :w{$i} OR d.name_en LIKE :w{$i} OR d.address_uk LIKE :w{$i} OR d.address_en LIKE :w{$i})")
                    ->bindValues(["wp{$i}" => $word . '%', "w{$i}" => '%' . $word . '%']);
            }

            return $select;
        };

        // Усі пункти групи, у легкому вигляді: ті, що вміщують кошик, мають іти на початку сторінки, навіть коли
        // їхні номери великі (кошик на 35 kg у Києві: більшість відділень з малими номерами беруть 30 kg).
        // Усе, що читає DivisionFit, інакше порядок «спершу ті, що вміщують» і "не вміщується" у списку розійдуться.
        $cols = ['d.id', 'd.category', 'd.max_weight', 'd.max_declared_cost', 'd.max_length', 'd.max_width', 'd.max_height'];
        $exact = self::exactNumber($query);
        if ($words !== null) {
            // Покупці знають номери відділень краще за адреси: спершу введений номер,
            // потім номери, що з нього починаються, потім збіги за адресою й назвою.
            $cols[] = "(CASE WHEN SUBSTRING_INDEX(d.number, '/', -1) = :exact THEN 0 WHEN SUBSTRING_INDEX(d.number, '/', -1) LIKE :prefix THEN 1 ELSE 2 END) AS match_rank";
        }
        $lightSelect = $filtered()->cols($cols);
        if ($words !== null) {
            $lightSelect->bindValue('exact', $exact)->bindValue('prefix', $words[0] . '%');
        }
        $light = $this->rows($lightSelect
            // "11/117": код міста, потім номер відділення, який знають покупці. `+ 0` робить це
            // числом; CAST(… AS …) aura прочитала б як аліас стовпця, і запит зламався б.
            ->orderBy(["SUBSTRING_INDEX(d.number, '/', -1) + 0", 'd.number']));
        $result['total'] = count($light);
        $ids = self::pageIds($light, $cart);
        $byId = [];
        if ($ids !== []) {
            foreach ($this->rows($this->queryFactory->newSelect()
                ->from(DivisionEntity::getTable() . ' AS d')
                ->cols(['d.id', 'd.number', 'd.category', 'd.name_uk', 'd.name_en', 'd.address_uk', 'd.address_en', 'd.max_weight', 'd.max_declared_cost', 'd.schedule'])
                ->where('d.id IN (:ids)')
                ->bindValue('ids', $ids)) as $row) {
                $byId[(int)$row->id] = $row;
            }
        }
        $points = array_values(array_filter(array_map(static fn(int $id): ?object => $byId[$id] ?? null, $ids)));
        $result['divisions'] = self::page($points, $cart, $lang);

        return $result;
    }

    /**
     * @param list<object> $groupRows рядки GroupEntity
     * @param array<string, int> $counts робочі пункти видачі за категоріями
     * @return list<array{id:int, name:string, count:int}> групи, які покупець може обрати, по порядку
     */
    public static function groupCounts(array $groupRows, array $counts): array
    {
        $groups = [];
        foreach ($groupRows as $group) {
            $count = 0;
            foreach (self::categoriesOf([$group], (int)$group->id) as $category) {
                $count += $counts[$category] ?? 0;
            }
            if ($count > 0) {
                $groups[] = ['id' => (int)$group->id, 'name' => (string)$group->name, 'count' => $count];
            }
        }

        return $groups;
    }

    /** @return list<string> категорії, показані в увімкненій групі */
    public static function categoriesOf(array $groupRows, ?int $groupId): array
    {
        foreach ($groupRows as $group) {
            if ((int)$group->id !== $groupId || !(int)$group->enabled) {
                continue;
            }
            $categories = json_decode((string)$group->categories, true);

            return is_array($categories) ? array_keys(array_filter($categories)) : [];
        }

        return [];
    }

    /**
     * Id однієї сторінки. Збіги пошуку йдуть за рангом (`match_rank`: введений номер, номери,
     * що з нього починаються, решта); усередині рангу спершу пункти, що вміщують кошик; інакше
     * лишається заданий порядок (за номером).
     *
     * @param list<object> $light рядки з id, max_weight, max_declared_cost, необов'язковим match_rank, у порядку номерів
     * @return list<int>
     */
    public static function pageIds(array $light, CartMetrics $cart): array
    {
        $buckets = [];
        foreach ($light as $row) {
            $rank = (int)($row->match_rank ?? 0);
            $buckets[$rank][DivisionFit::problems($cart, $row) === [] ? 0 : 1][] = (int)$row->id;
        }
        ksort($buckets);
        $ids = [];
        foreach ($buckets as $parts) {
            array_push($ids, ...($parts[0] ?? []), ...($parts[1] ?? []));
            if (count($ids) >= self::PAGE) {
                break;
            }
        }

        return array_slice($ids, 0, self::PAGE);
    }

    /** Номер відділення, як його введено ("№ 13" → "13"), для рангу точного номера. */
    private static function exactNumber(string $query): string
    {
        return preg_replace('/\D+/', '', mb_substr($query, 0, 64)) ?? '';
    }

    /** @param list<object> $points рядки DivisionEntity, уже в порядку сторінки (pageIds) */
    public static function page(array $points, CartMetrics $cart, string $lang): array
    {
        $english = $lang === 'en';
        $page = [];
        foreach ($points as $point) {
            $problems = array_map(static fn(FitProblem $p): string => $p->value, DivisionFit::problems($cart, $point));
            // Порожні поля пропускаються: сторінка надсилається на кожне натискання клавіші в пошуку.
            $page[] = array_filter(['id' => (int)$point->id, 'category' => (string)$point->category] + self::describe($point, $english) + [
                'maxWeight' => is_numeric($point->max_weight) && (float)$point->max_weight > 0 && (float)$point->max_weight <= 100000 ? (float)$point->max_weight : null,
                'problems' => $problems,
            ], static fn($value): bool => $value !== null && $value !== []);
        }

        return $page;
    }

    /**
     * Що покупець читає про пункт видачі — у списку й на картці обраного пункту.
     * @return array{title:string, place:?string, schedule:list<array{0:int,1:int,2:?string}>|true|null}
     */
    public static function describe(object $point, bool $english): array
    {
        $name = (string)(($english && ($point->name_en ?? '') ? $point->name_en : ($point->name_uk ?? '')) ?: ($point->number ?? ''));
        // Назви НП мають вигляд "Відділення №3 (до 30 кг на одне місце): вул. Слобожанська, 13".
        [$title, $place] = array_pad(explode(': ', $name, 2), 2, null);

        return [
            'title' => PointTitle::short($title),
            'place' => $place ?? (($english && ($point->address_en ?? '') ? $point->address_en : ($point->address_uk ?? '')) ?: null),
            'schedule' => self::compactSchedule(WorkSchedule::groups((string)($point->schedule ?? ''))),
        ];
    }

    public static function isTestPoint(string $name): bool
    {
        return mb_stripos($name, self::TEST_MARK) !== false;
    }

    /**
     * Введений пошук як слова для LIKE, щонайбільше 5. "№" і коми лише розділяють: "№ 13" — це відділення 13.
     * @return ?non-empty-list<string>
     */
    public static function searchWords(string $raw): ?array
    {
        // Назви в довіднику використовують ASCII-апостроф; телефони вводять ’ або ʼ.
        $raw = str_replace(['’', 'ʼ', '`', '‘'], "'", $raw);
        $text = mb_substr(trim(preg_replace('/[\s,№#]+/u', ' ', $raw) ?? ''), 0, self::MAX_SEARCH);
        $words = array_slice(array_values(array_filter(explode(' ', $text), static fn(string $w): bool => $w !== '')), 0, self::MAX_WORDS);

        return $words === [] ? null : array_map(static fn(string $w): string => addcslashes($w, '\\%_'), $words);
    }

    private function base(int $settlementId): \Aura\SqlQuery\Common\SelectInterface
    {
        return $this->queryFactory->newSelect()
            ->from(DivisionEntity::getTable() . ' AS d')
            ->where('d.settlement_id = :settlement')
            ->where("d.status = 'Working'")
            ->where('d.prohibited_issuance = 0')
            ->where('d.name_uk NOT LIKE :test_mark')->bindValue('test_mark', '%' . self::TEST_MARK . '%')
            ->bindValue('settlement', $settlementId);
    }

    /** @return list<object> */
    private function rows(\Aura\SqlQuery\Common\SelectInterface $select): array
    {
        return $this->db->query($select) === false ? [] : $this->db->results();
    }

    private function isDictionaryEmpty(): bool
    {
        $rows = $this->rows($this->queryFactory->newSelect()->cols(['1 AS x'])->from(DivisionEntity::getTable())->limit(1));

        return $rows === [];
    }

    /**
     * Сторінка надсилається на кожне натискання клавіші в пошуку: групи як [from, to, hours] і цілодобовий
     * пункт як true тримають сторінку на 100 пунктів невеликою.
     * @return list<array{0:int, 1:int, 2:?string}>|true|null
     */
    private static function compactSchedule(?array $groups): array|bool|null
    {
        if ($groups === null) {
            return null;
        }
        if (isset($groups[0]['allDay'])) {
            return true;
        }

        return array_map(static fn(array $g): array => [$g['from'], $g['to'], $g['hours']], $groups);
    }
}
