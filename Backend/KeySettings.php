<?php

namespace Okay\Modules\Sviat\NovaPoshta\Backend;

use Okay\Core\Settings;
use Okay\Modules\Sviat\NovaPoshta\Api\ApiKey;
use Okay\Modules\Sviat\NovaPoshta\KeyCheck\KeyAccess;
use Okay\Modules\Sviat\NovaPoshta\KeyCheck\KeyChecker;
use Okay\Modules\Sviat\NovaPoshta\KeyCheck\KeyCheckResult;

/** Що відбувається з ключем, коли менеджер натискає кнопку на сторінці налаштувань. */
final class KeySettings
{
    public function __construct(
        private readonly Settings $settings,
        private readonly ApiKey $apiKey,
        private readonly KeyChecker $checker,
    ) {
    }

    /** @return KeySubmission|null null, коли не було чого перевіряти */
    public function submit(string $input, bool $recheck): ?KeySubmission
    {
        $key = ApiKey::normalize($input);
        if ($key !== '') {
            $result = $this->checker->check($key);
            // Ключ, який Нова Пошта відхиляє, не повинен замінити той, що ще може працювати: одна хибна
            // вставка зупинила б магазин. Неперевірений або частково робочий ключ усе одно зберігається.
            $saved = $result->newApi !== KeyAccess::InvalidKey && $result->oldApi !== KeyAccess::InvalidKey;
            if ($saved) {
                $this->settings->set(ApiKey::SETTING, $key);
                $this->checker->remember($result);
            }

            return new KeySubmission($result, $saved);
        }

        $current = $this->apiKey->value();
        if (!$recheck || $current === null) {
            return null;
        }

        $result = $this->checker->check($current);
        $this->checker->remember($result);

        return new KeySubmission($result, saved: false, recheck: true);
    }

    public function lastResult(): ?KeyCheckResult
    {
        return $this->checker->lastResult();
    }
}
