<?php

namespace Okay\Modules\Sviat\NovaPoshta\Waybills;

/** The answer to the manager's create / attach / remove. */
final readonly class WaybillOutcome
{
    /**
     * @param list<array{field: ?string, message: ?string, advice: string, details: string}> $issues
     * @param list<string> $notices language keys of what the manager should know about a made waybill
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

    /** A refusal of the module's own, with an optional value shown with it (a number, an order id). */
    public static function refused(string $key, ?string $value = null): self
    {
        return self::failed(['field' => null, 'message' => $value, 'advice' => $key, 'details' => '']);
    }
}
