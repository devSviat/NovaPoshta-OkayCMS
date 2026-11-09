<?php

namespace Okay\Modules\Sviat\NovaPoshta\Cart;

use Okay\Core\EntityFactory;
use Okay\Core\Modules\Module;
use Okay\Entities\DeliveriesEntity;
use Okay\Modules\Sviat\NovaPoshta\Entities\DivisionEntity;
use Okay\Modules\Sviat\NovaPoshta\Entities\SettlementEntity;
use Okay\Modules\Sviat\NovaPoshta\Groups\GroupsStore;
use Okay\Modules\Sviat\NovaPoshta\Backend\ShippingSettings;

/**
 * Перевіряє на сервері те, що каже форма кошика: браузер може надіслати будь-які id. Формі
 * не довіряємо ні в чому, крім двох id, і лише для способів доставки цього модуля.
 */
final class CartSelectionValidator
{
    public function __construct(
        private readonly EntityFactory $entityFactory,
        private readonly GroupsStore $groups,
        private readonly Module $module,
        private readonly SettlementSearch $settlements,
        private readonly ShippingSettings $shipping,
    ) {
    }

    /** @return CartSelection|string вибір або мовний ключ помилки, яку треба показати */
    public function validate(object $order, array $post, CartMetrics $cart): CartSelection|string
    {
        $deliveryId = (int)($order->delivery_id ?? 0);
        $delivery = $deliveryId > 0 ? $this->entityFactory->get(DeliveriesEntity::class)->get($deliveryId) : null;
        if ($delivery && is_string($delivery->settings ?? null)) {
            $delivery->settings = @unserialize($delivery->settings, ['allowed_classes' => false]) ?: [];
        }
        [$settlementId, $divisionId] = self::postedIds($post, $deliveryId);
        $settlement = $settlementId > 0 ? $this->entityFactory->get(SettlementEntity::class)->get($settlementId) : null;
        $dictionaryReady = $this->settlements->hasAny();
        $address = self::checkAddress($delivery ?: null, (int)$this->module->getModuleIdByNamespace(__CLASS__), $settlement ?: null, $post, $this->shipping->read()->showLifting, $dictionaryReady);
        if (is_string($address) || $address->applies()) {
            return $address;
        }
        $division = $divisionId > 0 ? $this->entityFactory->get(DivisionEntity::class)->get($divisionId) : null;

        return self::check(
            $delivery ?: null,
            (int)$this->module->getModuleIdByNamespace(__CLASS__),
            $settlement ?: null,
            $division ?: null,
            $this->groups->all(),
            $cart,
            $dictionaryReady,
        );
    }

    /**
     * Пункт видачі, який відновлює кошик (останнє замовлення, надіслана форма), або null, коли
     * оформлення замовлення зараз його відхилило б.
     */
    public function restorable(object $delivery, ?object $settlement, ?object $division, CartMetrics $cart): ?object
    {
        return self::keepRestored($delivery, (int)$this->module->getModuleIdByNamespace(__CLASS__), $settlement, $division, $this->groups->all(), $cart);
    }

    /** @param list<object> $groupRows */
    public static function keepRestored(object $delivery, int $moduleId, ?object $settlement, ?object $division, array $groupRows, CartMetrics $cart): ?object
    {
        return $division !== null && self::check($delivery, $moduleId, $settlement, $division, $groupRows, $cart) instanceof CartSelection ? $division : null;
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
        // До першого запуску довідника кошик просить вказати відділення в коментарі до замовлення.
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
        if ($division->status !== 'Working' || (int)$division->prohibited_issuance === 1 || !in_array($division->category, $shownCategories, true)
            || DivisionList::isTestPoint((string)($division->name_uk ?? ''))) {
            return 'error_point_unavailable';
        }
        $problems = DivisionFit::problems($cart, $division);
        if ($problems !== []) {
            return 'error_point_' . $problems[0]->value;
        }

        return new CartSelection($delivery, $settlement, $division);
    }

    /** Адресний режим способу цього модуля: надіслана адреса, перевірена; решта цієї перевірки не стосується. */
    public static function checkAddress(?object $delivery, int $moduleId, ?object $settlement, array $post, bool $liftingOffered, bool $dictionaryReady = true): CartSelection|string
    {
        if ($delivery === null || (int)($delivery->module_id ?? 0) !== $moduleId || $moduleId === 0) {
            return CartSelection::none();
        }
        $settings = is_array($delivery->settings ?? null) ? $delivery->settings : [];
        if (DeliveryMode::fromServiceType($settings['service_type'] ?? null) !== DeliveryMode::Address) {
            return CartSelection::none();
        }
        // До першого запуску довідника кошик показує повідомлення замість форми, як і для пунктів видачі.
        if (!$dictionaryReady) {
            return CartSelection::none();
        }
        $parsed = AddressInput::parse($post, (int)$delivery->id, $liftingOffered);
        if ($parsed['error'] !== null) {
            return $parsed['error'];
        }
        if ($settlement === null || (int)$settlement->id !== $parsed['address']->settlementId) {
            return 'error_city';
        }

        return new CartSelection($delivery, $settlement, null, $parsed['address']);
    }

    /** @return array{0:int, 1:int} id населеного пункту й пункту видачі, надіслані для цього способу доставки */
    public static function postedIds(array $post, int $deliveryId): array
    {
        $choice = is_array($post['novaposhta'] ?? null) && is_array($post['novaposhta'][$deliveryId] ?? null) ? $post['novaposhta'][$deliveryId] : [];

        return [(int)($choice['settlement_id'] ?? 0), (int)($choice['division_id'] ?? 0)];
    }
}
