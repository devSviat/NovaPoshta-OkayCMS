<?php

namespace Okay\Modules\Sviat\NovaPoshta\Backend\Controllers;

use Okay\Admin\Controllers\IndexAdmin;
use Okay\Modules\Sviat\NovaPoshta\Api\ApiKey;
use Okay\Modules\Sviat\NovaPoshta\Backend\KeySettings;
use Okay\Modules\Sviat\NovaPoshta\Backend\KeySettingsView;

class NovaPoshtaSettingsAdmin extends IndexAdmin
{
    public function fetch(ApiKey $apiKey, KeySettings $keySettings)
    {
        $submission = null;
        // Stock 4.5.3 checks session_id only after the controller has run, so a forged
        // POST would already have changed the key; check it here before acting.
        if ($this->request->method('post') && $this->request->checkSession()) {
            $submission = $keySettings->submit(
                (string)$this->request->post('api_key'),
                (bool)$this->request->post('recheck'),
            );
        }

        $this->design->assign('np_key', KeySettingsView::vars($apiKey->value(), $keySettings->lastResult(), $submission));
        $this->response->setContent($this->design->fetch('settings.tpl'));
    }
}
