<?php

namespace Okay\Modules\Sviat\NovaPoshta\KeyCheck;

final readonly class KeyCheckResult
{
    public function __construct(
        public KeyAccess $newApi,
        public KeyAccess $oldApi,
        public \DateTimeImmutable $checkedAt,
    ) {
    }

    /** Модулю потрібні обидва API: одного з них недостатньо. */
    public function isUsable(): bool
    {
        return $this->newApi === KeyAccess::Ok && $this->oldApi === KeyAccess::Ok;
    }

    public function toJson(): string
    {
        return json_encode(['new' => $this->newApi->value, 'old' => $this->oldApi->value, 'at' => $this->checkedAt->getTimestamp()]);
    }

    public static function fromJson(string $json): ?self
    {
        $data = json_decode($json, true);
        if (!is_array($data) || !is_int($data['at'] ?? null)) {
            return null;
        }
        $new = KeyAccess::tryFrom((string)($data['new'] ?? ''));
        $old = KeyAccess::tryFrom((string)($data['old'] ?? ''));

        return $new && $old ? new self($new, $old, new \DateTimeImmutable('@' . $data['at'])) : null;
    }
}
