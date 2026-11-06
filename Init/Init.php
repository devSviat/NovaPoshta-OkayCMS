<?php

namespace Okay\Modules\Sviat\NovaPoshta\Init;

use Okay\Core\Modules\AbstractInit;

class Init extends AbstractInit
{
    public function install()
    {
        // Not a delivery module yet (MODULE_TYPE_DELIVERY comes with the cart):
        // otherwise a delivery method could be bound to a module that cannot deliver.
        $this->setBackendMainController('NovaPoshtaSettingsAdmin');
    }

    public function init()
    {
        $this->registerBackendController('NovaPoshtaSettingsAdmin');
        $this->addBackendControllerPermission('NovaPoshtaSettingsAdmin', 'delivery');
    }
}
