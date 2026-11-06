<?php

namespace Okay\Modules\Sviat\NovaPoshta\Backend;

use Okay\Core\Settings;
use Okay\Modules\Sviat\NovaPoshta\Api\ApiKey;
use Okay\Modules\Sviat\NovaPoshta\KeyCheck\KeyAccess;
use Okay\Modules\Sviat\NovaPoshta\KeyCheck\KeyChecker;
use Okay\Modules\Sviat\NovaPoshta\KeyCheck\KeyCheckResult;

/** What happens to the key when the manager presses a button on the settings page. */
final class KeySettings
{
    public function __construct(
        private readonly Settings $settings,
        private readonly ApiKey $apiKey,
        private readonly KeyChecker $checker,
    ) {
    }

    /** @return KeySubmission|null null when there was nothing to check */
    public function submit(string $input, bool $recheck): ?KeySubmission
    {
        $key = ApiKey::normalize($input);
        if ($key !== '') {
            $result = $this->checker->check($key);
            // A key Nova Poshta rejects must not replace one that may still work: one wrong
            // paste would stop the shop. An unchecked or partly working key is still saved.
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
