<?php

namespace Okay\Modules\Sviat\NovaPoshta\Init;

use Okay\Core\Modules\AbstractInit;
use Okay\Core\Modules\EntityField;
use Okay\Modules\Sviat\NovaPoshta\Storage\StateEntity;

class Init extends AbstractInit
{
    public function install()
    {
        // Not a delivery module yet (MODULE_TYPE_DELIVERY comes with the cart):
        // otherwise a delivery method could be bound to a module that cannot deliver.
        $this->setBackendMainController('NovaPoshtaSettingsAdmin');

        $this->migrateEntityTable(StateEntity::class, [
            new EntityField('id')->setIndexPrimaryKey()->setTypeInt(11, false)->setAutoIncrement(),
            new EntityField('name')->setTypeVarchar(64, false)->setIndexUnique(),
            new EntityField('value')->setTypeMediumText()->setNullable(),
            new EntityField('updated_at')->setTypeDatetime(false),
        ]);
    }

    public function init()
    {
        $this->registerBackendController('NovaPoshtaSettingsAdmin');
        $this->addBackendControllerPermission('NovaPoshtaSettingsAdmin', 'delivery');
    }
}
