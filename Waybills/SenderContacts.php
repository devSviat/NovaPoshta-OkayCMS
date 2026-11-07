<?php

namespace Okay\Modules\Sviat\NovaPoshta\Waybills;

use Okay\Modules\Sviat\NovaPoshta\Api\NpApiException;
use Okay\Modules\Sviat\NovaPoshta\Api\OldApi\OldApiClient;
use Okay\Modules\Sviat\NovaPoshta\Storage\StateStorage;

/**
 * The key owner's sender counterparty and its contact persons (old API: the new one has no
 * such list). Kept for an hour: the settings page and every waybill need them.
 */
final class SenderContacts
{
    private const string STATE = 'waybill_sender';
    private const int TTL = 3600;
    private const int TIMEOUT_SECONDS = 10;

    /** @var array{at:int, sender:?array{ref:string, name:string}, contacts:list<array{ref:string, name:string, phone:string}>}|null */
    private ?array $loaded = null;

    /** @param ?\Closure(): int $now */
    public function __construct(
        private readonly OldApiClient $api,
        private readonly StateStorage $state,
        private readonly ?\Closure $now = null,
    ) {
    }

    /** @return ?array{ref:string, name:string} */
    public function sender(): ?array
    {
        return $this->load()['sender'];
    }

    /** @return list<array{ref:string, name:string, phone:string}> */
    public function contacts(): array
    {
        return $this->load()['contacts'];
    }

    /** @throws SenderContactsUnavailable */
    private function load(): array
    {
        $now = $this->now ? ($this->now)() : time();
        if ($this->loaded === null) {
            $cached = json_decode((string)$this->state->get(self::STATE), true);
            $this->loaded = is_array($cached) && is_int($cached['at'] ?? null) ? $cached : null;
        }
        if ($this->loaded !== null && $now - $this->loaded['at'] < self::TTL) {
            return $this->loaded;
        }

        try {
            $api = $this->api->quick(self::TIMEOUT_SECONDS);
            $row = $api->call('Counterparty', 'getCounterparties', ['CounterpartyProperty' => 'Sender', 'Page' => '1'])[0] ?? null;
            $sender = is_array($row) && is_string($row['Ref'] ?? null) ? ['ref' => $row['Ref'], 'name' => (string)($row['Description'] ?? '')] : null;
            $contacts = [];
            if ($sender !== null) {
                foreach ($api->call('Counterparty', 'getCounterpartyContactPersons', ['Ref' => $sender['ref']]) as $person) {
                    if (is_array($person) && is_string($person['Ref'] ?? null)) {
                        $contacts[] = ['ref' => $person['Ref'], 'name' => (string)($person['Description'] ?? ''), 'phone' => (string)($person['Phones'] ?? '')];
                    }
                }
            }
        } catch (NpApiException $e) {
            throw new SenderContactsUnavailable($e->getMessage(), 0, $e);
        }

        $this->loaded = ['at' => $now, 'sender' => $sender, 'contacts' => $contacts];
        $this->state->set(self::STATE, json_encode($this->loaded, JSON_UNESCAPED_UNICODE));

        return $this->loaded;
    }
}
