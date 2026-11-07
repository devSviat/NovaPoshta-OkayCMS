<?php

namespace Okay\Modules\Sviat\NovaPoshta\Orders;

use Okay\Modules\Sviat\NovaPoshta\Waybills\MassReport;
use Okay\Modules\Sviat\NovaPoshta\Waybills\MassWaybills;

/**
 * «Create Nova Poshta waybills» of the orders list. The core passes list actions to no module,
 * so the posted action is taken from the list's own filter hook; the report waits in the session
 * for the page the browser is sent back to.
 */
final class OrdersMassAction
{
    public const string ACTION = 'sviat_np_create_waybills';
    private const string SESSION_KEY = 'sviat_np_mass';

    public function __construct(private readonly MassWaybills $mass)
    {
    }

    /**
     * @param bool $sessionOk the admin's CSRF token matched: stock checks it only after the controller
     * @return bool the action ran and the page must be reloaded by a redirect
     */
    public function handle(array $post, bool $sessionOk, array &$session): bool
    {
        if (($post['action'] ?? null) !== self::ACTION || !is_array($post['check'] ?? null) || !$sessionOk) {
            return false;
        }
        // A closed tab must not stop a request between the reservation and Nova Poshta's answer.
        ignore_user_abort(true);
        $session[self::SESSION_KEY] = $this->mass->run($post['check'])->toArray();

        return true;
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
     * @param \Closure(string): string $text language key without the module prefix → text
     * @return array{title: string, rows: list<array{orderId: int, kind: string, text: string}>, untouched: ?string}
     */
    public static function view(MassReport $report, \Closure $text): array
    {
        $rows = array_map(static function (array $row) use ($text): array {
            $value = $row['value'] ?? '';
            $line = match ($row['kind']) {
                'created' => sprintf($text('mass_created'), self::spaced($value)),
                'skipped' => $text('mass_skipped') . ': ' . sprintf($text((string)$row['key']), self::spaced($value)),
                default => $text('mass_failed') . ': ' . implode(' ', array_filter([
                    $value !== '' ? rtrim($value, '. ') . '.' : '',
                    $row['key'] !== null ? $text($row['key']) : '',
                ])),
            };

            return ['orderId' => $row['orderId'], 'kind' => $row['kind'], 'text' => $line];
        }, $report->rows);

        return [
            'title' => sprintf($text('mass_title'), $report->created(), $report->processed()),
            'rows' => $rows,
            'untouched' => $report->untouched > 0 ? sprintf($text('mass_untouched'), MassWaybills::LIMIT, $report->untouched) : null,
        ];
    }

    private static function spaced(string $number): string
    {
        return preg_match('/^\d{14}$/', $number) ? substr($number, 0, 2) . ' ' . implode(' ', str_split(substr($number, 2), 4)) : $number;
    }
}
