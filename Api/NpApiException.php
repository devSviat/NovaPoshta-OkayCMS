<?php

namespace Okay\Modules\Sviat\NovaPoshta\Api;

final class NpApiException extends \RuntimeException
{
    /**
     * @param list<string> $codes коди помилок Нової Пошти в отриманому вигляді
     * @param list<string> $npMessages тексти Нової Пошти, секрети замасковано
     */
    private function __construct(
        public readonly NpErrorKind $kind,
        public readonly array $codes,
        public readonly array $npMessages,
        string $message,
    ) {
        parent::__construct($message);
    }

    /**
     * @param list<string> $npMessages
     * @param list<string> $codes
     * @param list<?string> $secrets значення, які всюди маскуються як ***: повідомлення потрапляє в логи
     */
    public static function fromNp(NpErrorKind $kind, array $npMessages, array $codes = [], array $secrets = []): self
    {
        $replace = array_fill_keys(array_filter($secrets, static fn(?string $s): bool => $s !== null && $s !== ''), '***');
        $masked = array_map(static fn(string $text): string => strtr($text, $replace), array_values($npMessages));

        return new self($kind, array_values(array_map(strval(...), $codes)), $masked, implode('; ', $masked) ?: $kind->name);
    }
}
