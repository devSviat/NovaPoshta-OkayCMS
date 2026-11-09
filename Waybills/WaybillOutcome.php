<?php

namespace Okay\Modules\Sviat\NovaPoshta\Waybills;

/** Відповідь на створення / прив'язування / видалення від менеджера. */
final readonly class WaybillOutcome
{
    /**
     * @param list<array{field: ?string, message: ?string, advice: string, details: string}> $issues
     * @param list<string> $notices мовні ключі того, що менеджеру варто знати про створену накладну
     */
    public function __construct(
        public bool $ok,
        public ?string $number = null,
        public array $issues = [],
        public array $notices = [],
        public ?AuditResult $audit = null,
    ) {
    }

    /** @param array{field: ?string, message: ?string, advice: string, details: string} $issue */
    public static function failed(array $issue): self
    {
        return new self(false, null, [$issue]);
    }

    /** Власна відмова модуля з необов'язковим значенням, що показується поруч (номер, id замовлення). */
    public static function refused(string $key, ?string $value = null): self
    {
        return self::failed(['field' => null, 'message' => $value, 'advice' => $key, 'details' => '']);
    }
}
