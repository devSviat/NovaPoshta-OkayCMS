<?php

namespace Okay\Modules\Sviat\NovaPoshta\Cart;

use Okay\Core\EntityFactory;
use Okay\Core\Modules\Module;
use Okay\Entities\DeliveriesEntity;
use Okay\Modules\Sviat\NovaPoshta\Entities\DivisionEntity;
use Okay\Modules\Sviat\NovaPoshta\Entities\SettlementEntity;
use Okay\Modules\Sviat\NovaPoshta\Groups\GroupsStore;

/**
 * Checks on the server what the cart form says: the browser can post any ids. Nothing from
 * the form is trusted beyond the two ids, and only for this module's delivery methods (§9).
 */
final class CartSelectionValidator
{
    public function __construct(
        private readonly EntityFactory $entityFactory,
        private readonly GroupsStore $groups,
        private readonly Module $module,
        private readonly SettlementSearch $settlements,
    ) {
    }

    /** @return CartSelection|string a selection, or the language key of the error to show */
    public function validate(object $order, array $post, CartMetrics $cart): CartSelection|string
    {
        $deliveryId = (int)($order->delivery_id ?? 0);
        $delivery = $deliveryId > 0 ? $this->entityFactory->get(DeliveriesEntity::class)->get($deliveryId) : null;
        if ($delivery && is_string($delivery->settings ?? null)) {
            $delivery->settings = @unserialize($delivery->settings, ['allowed_classes' => false]) ?: [];
        }
        [$settlementId, $divisionId] = self::postedIds($post, $deliveryId);
        $settlement = $settlementId > 0 ? $this->entityFactory->get(SettlementEntity::class)->get($settlementId) : null;
        $division = $divisionId > 0 ? $this->entityFactory->get(DivisionEntity::class)->get($divisionId) : null;

        return self::check(
            $delivery ?: null,
            (int)$this->module->getModuleIdByNamespace(__CLASS__),
            $settlement ?: null,
            $division ?: null,
            $this->groups->all(),
            $cart,
            $this->settlements->hasAny(),
        );
    }

    /** @param list<object> $groupRows */
    public static function check(?object $delivery, int $moduleId, ?object $settlement, ?object $division, array $groupRows, CartMetrics $cart, bool $dictionaryReady = true): CartSelection|string
    {
        if ($delivery === null || (int)($delivery->module_id ?? 0) !== $moduleId || $moduleId === 0) {
            return CartSelection::none();
        }
        $settings = is_array($delivery->settings ?? null) ? $delivery->settings : [];
        if (DeliveryMode::fromServiceType($settings['service_type'] ?? null) !== DeliveryMode::Points) {
            return CartSelection::none();
        }
        // Before the first dictionary run the cart asks for the branch in the order comment.
        if (!$dictionaryReady) {
            return CartSelection::none();
        }
        if ($settlement === null) {
            return 'error_city';
        }
        if ($division === null || (int)$division->settlement_id !== (int)$settlement->id) {
            return 'error_point';
        }
        $shownCategories = [];
        foreach ($groupRows as $group) {
            array_push($shownCategories, ...DivisionList::categoriesOf($groupRows, (int)$group->id));
        }
        if ($division->status !== 'Working' || (int)$division->prohibited_issuance === 1 || !in_array($division->category, $shownCategories, true)) {
            return 'error_point_unavailable';
        }
        $problems = DivisionFit::problems($cart, $division);
        if ($problems !== []) {
            return 'error_point_' . $problems[0]->value;
        }

        return new CartSelection($delivery, $settlement, $division);
    }

    /** @return array{0:int, 1:int} settlement and point ids posted for this delivery method */
    public static function postedIds(array $post, int $deliveryId): array
    {
        $choice = is_array($post['novaposhta'] ?? null) && is_array($post['novaposhta'][$deliveryId] ?? null) ? $post['novaposhta'][$deliveryId] : [];

        return [(int)($choice['settlement_id'] ?? 0), (int)($choice['division_id'] ?? 0)];
    }
}
