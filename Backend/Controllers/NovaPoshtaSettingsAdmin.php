<?php

namespace Okay\Modules\Sviat\NovaPoshta\Backend\Controllers;

use Okay\Admin\Controllers\IndexAdmin;
use Okay\Core\Languages;
use Okay\Modules\Sviat\NovaPoshta\Api\ApiKey;
use Okay\Modules\Sviat\NovaPoshta\Backend\DictionaryStatusView;
use Okay\Modules\Sviat\NovaPoshta\Backend\KeySettings;
use Okay\Modules\Sviat\NovaPoshta\Backend\KeySettingsView;
use Okay\Modules\Sviat\NovaPoshta\Cart\PopularCities;
use Okay\Modules\Sviat\NovaPoshta\Cart\SettlementSearch;
use Okay\Modules\Sviat\NovaPoshta\Dictionaries\DictionaryCounts;
use Okay\Modules\Sviat\NovaPoshta\Dictionaries\DictionaryRunner;
use Okay\Modules\Sviat\NovaPoshta\Dictionaries\DictionaryStatus;
use Okay\Modules\Sviat\NovaPoshta\Groups\GroupsInput;
use Okay\Modules\Sviat\NovaPoshta\Groups\GroupsStore;
use Okay\Modules\Sviat\NovaPoshta\Groups\GroupsView;
use Okay\Modules\Sviat\NovaPoshta\Storage\ModuleState;

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
    ) {
        $submission = null;
        // Stock 4.5.3 checks session_id only after the controller has run, so a forged
        // POST would already have acted; check it here before acting.
        if ($this->request->method('post') && $this->request->checkSession()) {
            switch ($this->request->post('np_action')) {
                case 'dictionaries':
                    $dictionaryRunner->requestRun();
                    break;
                case 'popular':
                    $ids = $this->request->post('popular');
                    $popularCities->save(is_array($ids) ? array_values($ids) : []);
                    $this->design->assign('np_popular_saved', true);
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

        $byCategory = $dictionaryCounts->byCategory();
        $this->design->assign('np_key', KeySettingsView::vars($apiKey->value(), $keySettings->lastResult(), $submission));
        $this->design->assign('np_dict', DictionaryStatusView::vars(
            DictionaryStatus::load($moduleState),
            time(),
            $dictionaryCounts->summary($byCategory),
        ));
        $this->design->assign('np_groups', GroupsView::vars($groupsStore->all(), $byCategory));
        $this->design->assign('np_popular', $popularCities->list((string)$languages->getLangLabel()));
        $this->response->setContent($this->design->fetch('settings.tpl'));
    }

    /** City suggestions for the popular-cities block (`controller=…@searchSettlements&q=`). */
    public function searchSettlements(SettlementSearch $search, Languages $languages)
    {
        $query = $this->request->get('q');
        $found = is_string($query) ? $search->find($query, (string)$languages->getLangLabel()) : [];
        $this->response->setContent(json_encode(['settlements' => $found], JSON_UNESCAPED_UNICODE), RESPONSE_JSON);
    }
}
