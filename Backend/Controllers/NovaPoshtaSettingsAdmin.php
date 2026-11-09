<?php

namespace Okay\Modules\Sviat\NovaPoshta\Backend\Controllers;

use Okay\Modules\Sviat\NovaPoshta\Backend\DeliveryWarnings;
use Okay\Entities\DeliveriesEntity;
use Okay\Core\Modules\Module;
use Okay\Admin\Controllers\IndexAdmin;
use Okay\Core\Languages;
use Okay\Modules\Sviat\NovaPoshta\Api\ApiKey;
use Okay\Modules\Sviat\NovaPoshta\Backend\DictionaryStatusView;
use Okay\Modules\Sviat\NovaPoshta\Backend\KeySettings;
use Okay\Modules\Sviat\NovaPoshta\Backend\KeySettingsView;
use Okay\Modules\Sviat\NovaPoshta\Backend\SenderDivisions;
use Okay\Modules\Sviat\NovaPoshta\Backend\ShippingSettings;
use Okay\Modules\Sviat\NovaPoshta\Entities\DivisionEntity;
use Okay\Modules\Sviat\NovaPoshta\Entities\SettlementEntity;
use Okay\Core\EntityFactory;
use Okay\Modules\Sviat\NovaPoshta\Payments\CodPayments;
use Okay\Modules\Sviat\NovaPoshta\Legacy\LegacyData;
use Okay\Modules\Sviat\NovaPoshta\Legacy\LegacyMigration;
use Okay\Modules\Sviat\NovaPoshta\Legacy\LegacyMigrationView;
use Okay\Modules\Sviat\NovaPoshta\Cart\PopularCities;
use Okay\Modules\Sviat\NovaPoshta\Cart\SettlementSearch;
use Okay\Modules\Sviat\NovaPoshta\Dictionaries\DictionaryCounts;
use Okay\Modules\Sviat\NovaPoshta\Dictionaries\DictionaryRunner;
use Okay\Modules\Sviat\NovaPoshta\Dictionaries\DictionaryStatus;
use Okay\Modules\Sviat\NovaPoshta\Groups\GroupsInput;
use Okay\Modules\Sviat\NovaPoshta\Groups\GroupsStore;
use Okay\Modules\Sviat\NovaPoshta\Groups\GroupsView;
use Okay\Modules\Sviat\NovaPoshta\Storage\ModuleState;
use Okay\Modules\Sviat\NovaPoshta\Waybills\SenderContacts;
use Okay\Modules\Sviat\NovaPoshta\Waybills\SenderContactsUnavailable;
use Okay\Modules\Sviat\NovaPoshta\Waybills\WaybillSettings;

class NovaPoshtaSettingsAdmin extends IndexAdmin
{
    public function fetch(
        ApiKey $apiKey,
        KeySettings $keySettings,
        DictionaryRunner $dictionaryRunner,
        DictionaryCounts $dictionaryCounts,
        GroupsStore $groupsStore,
        ModuleState $moduleState,
        PopularCities $popularCities,
        Languages $languages,
        ShippingSettings $shippingSettings,
        SenderDivisions $senderDivisions,
        EntityFactory $entityFactory,
        CodPayments $codPayments,
        WaybillSettings $waybillSettings,
        SenderContacts $senderContacts,
        LegacyMigration $legacyMigration,
        Module $module,
        LegacyData $legacyData,
    ) {
        $submission = null;
        // Stock 4.5.3 checks session_id only after the controller has run, so a forged
        // POST would already have acted; check it here before acting.
        if ($this->request->method('post') && $this->request->checkSession()) {
            switch ($this->request->post('np_action')) {
                case 'dictionaries':
                    $dictionaryRunner->requestRun();
                    break;
                case 'legacy_migrate':
                    $legacyMigration->request();
                    break;
                case 'popular':
                    $ids = $this->request->post('popular');
                    $popularCities->save(is_array($ids) ? array_values($ids) : []);
                    $this->design->assign('np_popular_saved', true);
                    break;
                case 'shipping':
                    $errors = $shippingSettings->save($_POST);
                    if ($errors === []) {
                        $this->design->assign('np_shipping_saved', true);
                    } else {
                        $this->design->assign('np_shipping_errors', $errors);
                    }
                    break;
                case 'waybill':
                    $errors = $waybillSettings->save($_POST);
                    if ($errors === []) {
                        $this->design->assign('np_waybill_saved', true);
                    } else {
                        $this->design->assign('np_waybill_errors', $errors);
                    }
                    break;
                case 'groups':
                    $input = GroupsInput::parse($_POST);
                    if ($input['errors'] === []) {
                        $groupsStore->save($input['groups']);
                        $this->design->assign('np_groups_saved', true);
                    } else {
                        $this->design->assign('np_groups_errors', $input['errors']);
                    }
                    break;
                default:
                    $submission = $keySettings->submit(
                        (string)$this->request->post('api_key'),
                        (bool)$this->request->post('recheck'),
                    );
            }
        }

        $this->design->assign('np_legacy', LegacyMigrationView::vars(
            $legacyMigration->state(),
            $legacyData->counts(),
            $legacyData->enabledModules(),
            $legacyData->legacyDeliveries(),
            $legacyMigration->stepNames(),
            $legacyMigration->problem(),
            $legacyMigration->stalled(),
        ));
        $byCategory = $dictionaryCounts->byCategory();
        $this->design->assign('np_key', KeySettingsView::vars($apiKey->value(), $keySettings->lastResult(), $submission));
        $this->design->assign('np_dict', DictionaryStatusView::vars(
            DictionaryStatus::load($moduleState),
            time(),
            $dictionaryCounts->summary($byCategory),
        ));
        $this->design->assign('np_groups', GroupsView::vars($groupsStore->all(), $byCategory));
        $lang = (string)$languages->getLangLabel();
        $this->design->assign('np_popular', $popularCities->list($lang));
        $config = $shippingSettings->read();
        $sender = $config->senderDivisionId ? $entityFactory->get(DivisionEntity::class)->get($config->senderDivisionId) : null;
        $senderCity = $sender ? $entityFactory->get(SettlementEntity::class)->get((int)$sender->settlement_id) : null;
        $this->design->assign('np_cod_payments', $codPayments->all());
        $this->design->assign('np_free_for_all', DeliveryWarnings::freeForAll(
            $entityFactory->get(DeliveriesEntity::class)->noLimit()->find(),
            (int)$module->getModuleIdByNamespace(__CLASS__),
        ));
        $this->design->assign('np_shipping', [
            'config' => $config,
            'city' => $senderCity ? self::cityView($senderCity, $lang) : null,
            'divisions' => $senderCity ? $senderDivisions->forSettlement((int)$senderCity->id, $lang) : [],
        ]);
        try {
            $waybillSender = ['sender' => $senderContacts->sender(), 'contacts' => $senderContacts->contacts(), 'unavailable' => false];
        } catch (SenderContactsUnavailable) {
            $waybillSender = ['sender' => null, 'contacts' => [], 'unavailable' => true];
        }
        $this->design->assign('np_waybill', ['config' => $waybillSettings->read()] + $waybillSender);
        $this->response->setContent($this->design->fetch('settings.tpl'));
    }

    /** @return array{id: int, name: string, type: string, title: string, area: string} the chosen city as the page shows it */
    private static function cityView(object $city, string $lang): array
    {
        $name = (string)($lang === 'en' && $city->name_en ? $city->name_en : $city->name_uk);
        [$type, $title] = \Okay\Modules\Sviat\NovaPoshta\Cart\SettlementName::split($name);

        return ['id' => (int)$city->id, 'name' => $name, 'type' => $type, 'title' => $title, 'area' => (string)($lang === 'en' && $city->area_en ? $city->area_en : ($city->area_uk ?? ''))];
    }

    /** City suggestions for the popular-cities block (`controller=…@searchSettlements&q=`). */
    public function searchSettlements(SettlementSearch $search, Languages $languages)
    {
        $query = $this->request->get('q');
        $found = is_string($query) ? $search->find($query, (string)$languages->getLangLabel()) : [];
        $this->response->setContent(json_encode(['settlements' => $found], JSON_UNESCAPED_UNICODE), RESPONSE_JSON);
    }

    /** Points of the sender's settlement for the sender dropdown (`controller=…@senderDivisions&settlement_id=`). */
    public function senderDivisions(SenderDivisions $divisions, Languages $languages)
    {
        $id = $this->request->get('settlement_id', 'integer');
        $this->response->setContent(json_encode(['divisions' => $divisions->forSettlement((int)$id, (string)$languages->getLangLabel())], JSON_UNESCAPED_UNICODE), RESPONSE_JSON);
    }
}
