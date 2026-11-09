<?php

namespace Okay\Modules\Sviat\NovaPoshta\Backend;

use Okay\Core\EntityFactory;
use Okay\Core\Settings;
use Okay\Modules\Sviat\NovaPoshta\Entities\DivisionEntity;
use Okay\Modules\Sviat\NovaPoshta\Quote\ShippingConfig;

/** Блок «Відправлення й розрахунок ціни» сторінки налаштувань. */
final class ShippingSettings
{
    public const string SENDER = 'sviat__novaposhta__sender_division_id';
    public const string WEIGHT = 'sviat__novaposhta__default_weight';
    public const string VOLUME = 'sviat__novaposhta__default_volume';
    public const string SHOW_TERM = 'sviat__novaposhta__show_term';
    public const string SHOW_LIFTING = 'sviat__novaposhta__show_lifting';

    private const float MAX_WEIGHT = 1000.0;
    private const float MAX_VOLUME = 10.0;

    public function __construct(
        private readonly Settings $settings,
        private readonly EntityFactory $entityFactory,
    ) {
    }

    public function read(): ShippingConfig
    {
        return ShippingConfig::fromSettings(fn(string $name) => $this->settings->get($name));
    }

    /** @return list<string> мовні ключі помилок; якщо вони є, нічого не зберігається */
    public function save(array $post): array
    {
        $parsed = self::parse($post, fn(int $id): bool => (bool)$this->entityFactory->get(DivisionEntity::class)->get($id));
        if ($parsed['errors'] === []) {
            foreach ($parsed['values'] as $name => $value) {
                $this->settings->set($name, $value);
            }
        }

        return $parsed['errors'];
    }

    /**
     * @param callable(int): bool $divisionExists
     * @return array{values: array<string, int|float|null>, errors: list<string>}
     */
    public static function parse(array $post, callable $divisionExists): array
    {
        $errors = [];
        $sender = self::number($post['sender_division_id'] ?? '');
        if ($sender !== null && ($sender <= 0 || (float)(int)$sender !== $sender || !$divisionExists((int)$sender))) {
            $errors[] = 'shipping_error_sender';
        }
        $weight = self::number($post['default_weight'] ?? null);
        if ($weight === null || $weight <= 0 || $weight > self::MAX_WEIGHT) {
            $errors[] = 'shipping_error_weight';
        }
        $volume = self::number($post['default_volume'] ?? null);
        if ($volume === null || $volume <= 0 || $volume > self::MAX_VOLUME) {
            $errors[] = 'shipping_error_volume';
        }

        return [
            'values' => [
                self::SENDER => $sender === null ? null : (int)$sender,
                self::WEIGHT => $weight,
                self::VOLUME => $volume,
                self::SHOW_TERM => empty($post['show_term']) ? 0 : 1,
                self::SHOW_LIFTING => empty($post['show_lifting']) ? 0 : 1,
            ],
            'errors' => $errors,
        ];
    }

    /** Приймає "1,5", як вводять в українській локалі; будь-що інше, що не є числом, дає null. */
    private static function number(mixed $raw): ?float
    {
        if (!is_string($raw) && !is_int($raw) && !is_float($raw)) {
            return null;
        }
        $text = str_replace(',', '.', trim((string)$raw));

        return is_numeric($text) ? (float)$text : null;
    }
}
