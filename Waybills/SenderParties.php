<?php

namespace Okay\Modules\Sviat\NovaPoshta\Waybills;

use Okay\Core\EntityFactory;
use Okay\Modules\Sviat\NovaPoshta\Backend\ShippingSettings;
use Okay\Modules\Sviat\NovaPoshta\Entities\DivisionEntity;

/** Відправник накладної з налаштувань і кабінету НП. */
final class SenderParties implements SenderSource
{
    public function __construct(
        private readonly SenderContacts $contacts,
        private readonly WaybillSettings $waybillSettings,
        private readonly ShippingSettings $shippingSettings,
        private readonly EntityFactory $entityFactory,
    ) {
    }

    public function party(): SenderParty|string
    {
        $divisionId = $this->shippingSettings->read()->senderDivisionId;
        $division = $divisionId ? $this->entityFactory->get(DivisionEntity::class)->get($divisionId) : null;
        if (!$division) {
            return 'waybill_no_sender_point';
        }
        try {
            $sender = $this->contacts->sender();
            $contacts = $this->contacts->contacts();
        } catch (SenderContactsUnavailable) {
            return 'waybill_sender_unavailable';
        }
        $config = $this->waybillSettings->read();
        $chosen = null;
        foreach ($contacts as $contact) {
            if ($config->contactRef === null || $contact['ref'] === $config->contactRef) {
                $chosen = $contact;
                break;
            }
        }
        $phone = $config->senderPhone ?? NpPhone::normalize((string)($chosen['phone'] ?? ''));
        if ($sender === null || $chosen === null || $phone === null) {
            return 'waybill_no_contact';
        }

        return new SenderParty($sender['ref'], $chosen['ref'], $chosen['name'], $phone, (int)$division->id, (string)$division->number);
    }
}
