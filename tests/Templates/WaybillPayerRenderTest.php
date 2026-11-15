<?php

namespace Modules\Sviat\NovaPoshta\Templates;

require_once __DIR__ . '/../Support/WaybillFixtures.php';

use Modules\Sviat\NovaPoshta\Support\WaybillFixtures as W;
use Okay\Modules\Sviat\NovaPoshta\Waybills\WaybillCard;
use Okay\Modules\Sviat\NovaPoshta\Waybills\WaybillDraft;
use PHPUnit\Framework\TestCase;
use Smarty\Smarty;

/** Блок накладної, відрендерений Smarty: причина платника у формі й картці та рядок переказу оплаченого замовлення. */
class WaybillPayerRenderTest extends TestCase
{
    /** @param array<string, string> $texts переклади замість «[ключ]» */
    private static function render(array $waybill, array $texts = []): string
    {
        $root = dirname(__DIR__, 5);
        $smarty = new Smarty();
        $smarty->setTemplateDir([$root . '/Okay/Modules/Sviat/NovaPoshta/Backend/design/html', $root . '/backend/design/html']);
        $smarty->setCompileDir(sys_get_temp_dir() . '/np-waybill-render-' . getmypid());
        // Адмінка не надсилає ключів, яких цей стан не має; Okay\Core\Design так само глушить ці попередження.
        $smarty->muteUndefinedOrNullWarnings();
        $smarty->registerPlugin(Smarty::PLUGIN_FUNCTION, 'get_design_block', static fn(): string => '');
        $smarty->assign('btr', new class ($texts) {
            public function __construct(private readonly array $texts)
            {
            }

            public function __get(string $key): string
            {
                return $this->getTranslation($key);
            }

            public function getTranslation(string $key): string
            {
                return $this->texts[$key] ?? '[' . $key . ']';
            }
        });
        $smarty->assign('np_waybill', $waybill);
        $session = $_SESSION ?? null;
        $_SESSION = ['id' => 'test-session'];
        try {
            return $smarty->fetch('waybill.tpl');
        } finally {
            $_SESSION = $session;
        }
    }

    private static function form(WaybillDraft $draft, bool $paid = false, bool $codPayment = true, array $texts = []): string
    {
        return self::render(['orderId' => 990061, 'row' => null, 'draft' => $draft, 'refusal' => null, 'paid' => $paid, 'codPayment' => $codPayment, 'textAddress' => false, 'duplicatePoint' => false], $texts);
    }

    /** @return ?string текст причини під селектом платника, null — якщо її немає */
    private static function reason(string $html): ?string
    {
        if (!preg_match('~<div class="np-wb__payer">.*?<div class="np-wb__reason"( hidden)?\s*>(.*?)</div>~s', $html, $m)) {
            return null;
        }

        return ($m[1] !== '' ? 'hidden:' : '') . $m[2];
    }

    public function testFormNamesWhyTheSenderPays(): void
    {
        self::assertSame('[sviat__novaposhta__waybill_payer_in_total]', self::reason(self::form(W::point(['payerType' => 'Sender', 'senderPaysBecause' => 'in_total', 'deliveryInTotal' => 95.0]))));
        self::assertSame('[sviat__novaposhta__waybill_payer_free]', self::reason(self::form(W::point(['payerType' => 'Sender', 'senderPaysBecause' => 'free']))));
        self::assertNull(self::reason(self::form(W::point(['payerType' => 'Sender']))), 'control: Sender from the settings');
        self::assertNull(self::reason(self::form(W::point())), 'control: Recipient from the settings');
    }

    public function testInTotalReasonCarriesTheSum(): void
    {
        $lang = [];
        include dirname(__DIR__, 5) . '/Okay/Modules/Sviat/NovaPoshta/Backend/lang/ua.php';
        $key = 'sviat__novaposhta__waybill_payer_in_total';
        $html = self::form(W::point(['payerType' => 'Sender', 'senderPaysBecause' => 'in_total', 'deliveryInTotal' => 95.5]), texts: [$key => $lang[$key]]);

        self::assertSame(str_replace('%sum%', '95,50', $lang[$key]), self::reason($html));
    }

    /** Менеджер повернув «Отримувач»: причина «Відправник» схована, попередження про подвійну оплату — ні. */
    public function testReasonStartsHiddenForAnotherPayer(): void
    {
        $html = self::form(W::point(['payerType' => 'Recipient', 'senderPaysBecause' => 'in_total', 'deliveryInTotal' => 95.0]));

        self::assertSame('hidden:[sviat__novaposhta__waybill_payer_in_total]', self::reason($html));
        self::assertMatchesRegularExpression('~<div class="alert alert--warning np-wb__double" role="alert"\s*>~', $html);
    }

    public function testPaidOrderShowsOneLineInsteadOfTheTransfer(): void
    {
        $paid = self::form(W::point(['paid' => true]), paid: true);
        self::assertStringContainsString('[sviat__novaposhta__waybill_cod_paid]', $paid);
        self::assertStringNotContainsString('data-field="cod"', $paid);
        self::assertStringNotContainsString('data-field="cod_amount"', $paid);
        self::assertStringNotContainsString('data-field="cod_payer"', $paid);
        self::assertStringContainsString('[sviat__novaposhta__waybill_cod_money_title]', $paid, 'the group title stays');

        $unpaid = self::form(W::point(['cod' => true]));
        self::assertStringNotContainsString('[sviat__novaposhta__waybill_cod_paid]', $unpaid, 'control: unpaid');
        self::assertStringContainsString('data-field="cod"', $unpaid);
        self::assertStringContainsString('data-field="cod_amount"', $unpaid);
        self::assertStringContainsString('data-field="cod_payer"', $unpaid);
        self::assertStringContainsString('[sviat__novaposhta__waybill_cod_auto]', $unpaid);
        self::assertStringContainsString('[sviat__novaposhta__waybill_cod_covered]', self::form(W::point(['codAmount' => 0.0])));
        self::assertStringContainsString('[sviat__novaposhta__waybill_cod_manual]', self::form(W::point(), codPayment: false));
    }

    public function testCardShowsTheStoredReasonNextToThePayer(): void
    {
        $card = static function (?array $services): string {
            $row = (object)['number' => '20451581331601', 'ref' => null, 'api' => 'old', 'status_code' => 7, 'tracked_at' => null, 'services' => $services === null ? null : json_encode($services)];

            return self::render(['orderId' => 990061, 'row' => [
                'number' => '20451581331601', 'numberSpaced' => '20 4515 8133 1601', 'attached' => false, 'deletable' => false,
                'trackingUrl' => 'https://novaposhta.ua/tracking/20451581331601/', 'card' => WaybillCard::fromDocument(W::fixture('status_cod'), $row),
                'services' => $services ?? [], 'audit' => null,
            ], 'draft' => null, 'refusal' => null]);
        };

        self::assertStringContainsString('<span class="np-wb__payer-why">[sviat__novaposhta__card_payer_reason_manual]</span>', $card(['payerType' => 'Recipient', 'payerReason' => 'manual']));
        self::assertStringNotContainsString('np-wb__payer-why', $card(['cod' => false]), 'control: an older waybill');
        self::assertStringContainsString('[sviat__novaposhta__card_payer]', $card(['cod' => false]));
    }
}
