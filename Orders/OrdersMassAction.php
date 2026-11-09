<?php

namespace Okay\Modules\Sviat\NovaPoshta\Orders;

use Okay\Modules\Sviat\NovaPoshta\Waybills\MassReport;
use Okay\Modules\Sviat\NovaPoshta\Waybills\MassWaybills;

/**
 * «Create Nova Poshta waybills» у списку замовлень. Ядро не передає дії списку жодному модулю,
 * тож надіслану дію беруть із хука фільтра самого списку; звіт чекає в сесії
 * на сторінку, на яку повертають браузер.
 */
final class OrdersMassAction
{
    public const string ACTION = 'sviat_np_create_waybills';
    private const string SESSION_KEY = 'sviat_np_mass';

    public function __construct(private readonly MassWaybills $mass)
    {
    }

    /**
     * @param bool $sessionOk CSRF-токен адміна збігся: сток перевіряє його лише після контролера
     * @return bool дію виконано, і сторінку треба перезавантажити редиректом
     */
    public function handle(array $post, bool $sessionOk, array &$session): bool
    {
        if (($post['action'] ?? null) !== self::ACTION || !is_array($post['check'] ?? null) || !$sessionOk) {
            return false;
        }
        // Закрита вкладка не має обривати запит між резервацією і відповіддю Нової Пошти.
        ignore_user_abort(true);
        $session[self::SESSION_KEY] = $this->mass->run($post['check'])->toArray();

        return true;
    }

    /**
     * handle() для поточного запиту. Сток 4.5.3 checkSession() очищає $_POST за хибного токена,
     * тому POST читається до перевірки.
     *
     * @param \Closure(): bool $checkSession
     */
    public function handleRequest(\Closure $checkSession, array &$session): bool
    {
        $post = $_POST;

        return $this->handle($post, (bool)$checkSession(), $session);
    }

    public static function take(array &$session): ?MassReport
    {
        if (!array_key_exists(self::SESSION_KEY, $session)) {
            return null;
        }
        $report = MassReport::fromArray($session[self::SESSION_KEY]);
        unset($session[self::SESSION_KEY]);

        return $report;
    }

    /**
     * @param \Closure(string): string $text мовний ключ без префікса модуля → текст
     * @return array{title: string, rows: list<array{orderId: int, kind: string, text: string}>, untouched: ?string}
     */
    public static function view(MassReport $report, \Closure $text): array
    {
        $rows = array_map(static function (array $row) use ($text): array {
            $value = $row['value'] ?? '';
            $line = match ($row['kind']) {
                'created' => sprintf($text('mass_created'), self::spaced($value)) . ($row['key'] !== null ? '. ' . $text($row['key']) : ''),
                'skipped' => $text('mass_skipped') . ': ' . sprintf($text((string)$row['key']), self::spaced($value)),
                default => $row['key'] === 'waybill_not_saved'
                    ? sprintf($text('mass_not_saved'), self::spaced($value))
                    : self::refusal($row, $text),
            };

            return ['orderId' => $row['orderId'], 'kind' => $row['kind'], 'text' => $line];
        }, $report->rows);

        return self::summary($report, $rows, $text);
    }

    private static function refusal(array $row, \Closure $text): string
    {
        $value = (string)($row['value'] ?? '');

        // Наша порада вже каже, що відхилила НП; її власний (англійський) текст — лише коли нашої немає.
        return $text('mass_failed') . ': ' . (self::specific($row['key']) || $value === ''
            ? $text((string)($row['key'] ?? 'waybill_advice_generic'))
            : rtrim($value, '. ') . '.');
    }

    private static function summary(MassReport $report, array $rows, \Closure $text): array
    {
        return [
            'title' => sprintf($text('mass_title'), $report->created(), $report->processed()),
            'rows' => $rows,
            'untouched' => $report->untouched > 0 ? sprintf($text('mass_untouched'), MassWaybills::LIMIT, $report->untouched) : null,
        ];
    }

    private static function specific(?string $key): bool
    {
        return $key !== null && $key !== 'waybill_advice_generic';
    }

    private static function spaced(string $number): string
    {
        return preg_match('/^\d{14}$/', $number) ? substr($number, 0, 2) . ' ' . implode(' ', str_split(substr($number, 2), 4)) : $number;
    }
}
