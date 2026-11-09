<?php

namespace Okay\Modules\Sviat\NovaPoshta\Waybills;

/** Що з відповіді getStatusDocuments зберігається разом із накладною. */
final class WaybillTrackingDocument
{
    /**
     * Нова відповідь із позначкою часу отримання. Телефон отримувача, який НП цього разу приховала (запит
     * з іншим телефоном), береться з попередньої відповіді: оновленню він потрібен, щоб бачити повні дані.
     */
    public static function merge(array $document, ?array $previous, string $now): array
    {
        if (trim((string)($document['PhoneRecipient'] ?? '')) === '' && is_array($previous) && trim((string)($previous['PhoneRecipient'] ?? '')) !== '') {
            $document['PhoneRecipient'] = $previous['PhoneRecipient'];
        }
        $document['_fetched_at'] = $now;

        return $document;
    }

    /**
     * Статус, який варто зберегти. «Номер не знайдено» (3) одразу після створення означає лише, що НП ще не
     * бачила накладну: його збереження зупинило б трекінг; його встановлює лише 30-денне правило трекера.
     */
    public static function status(array $document): ?int
    {
        $code = (int)($document['StatusCode'] ?? 0);

        return $code > 0 && $code !== WaybillStatus::NOT_FOUND ? $code : null;
    }

    /**
     * Колонки статусу для запису з відповіді getStatusDocuments; порожньо, коли в ній немає статусу, вартого збереження.
     * Змінений статус отримує дату сканування, що його спричинило (НП: DateScan, "H:i d.m.Y" за київським часом),
     * або жодної: дата попереднього статусу датувала б його неправильно. Незмінений статус зберігає дату трекера.
     *
     * @return array{status_code?: int, status_text?: string, status_at?: ?string}
     */
    public static function statusColumns(array $document, ?object $row): array
    {
        $code = self::status($document);
        if ($code === null) {
            return [];
        }
        $columns = ['status_code' => $code, 'status_text' => mb_substr((string)($document['Status'] ?? ''), 0, 255)];
        if ($row === null || (int)($row->status_code ?? 0) !== $code) {
            $columns['status_at'] = self::scanDate($document['DateScan'] ?? null);
        }

        return $columns;
    }

    /** DateScan НП ("H:i d.m.Y", київський час) — дата сканування, що спричинило статус, — у часі магазину. */
    public static function scanDate(mixed $value): ?string
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }
        $date = \DateTimeImmutable::createFromFormat('!H:i d.m.Y', trim($value), new \DateTimeZone('Europe/Kyiv'));

        return $date === false ? null : $date->setTimezone(new \DateTimeZone(date_default_timezone_get()))->format('Y-m-d H:i:s');
    }
}
