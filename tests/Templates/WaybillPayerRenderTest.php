<?php

namespace Modules\Sviat\NovaPoshta\Templates;

require_once __DIR__ . '/../Support/WaybillFixtures.php';

use Modules\Sviat\NovaPoshta\Support\WaybillFixtures as W;
use Okay\Modules\Sviat\NovaPoshta\Waybills\WaybillCard;
use Okay\Modules\Sviat\NovaPoshta\Waybills\WaybillDraft;
use PHPUnit\Framework\TestCase;

/** Блок накладної, відрендерений Smarty: причина платника, рядок переказу оплаченого замовлення, заголовок і знак картки. */
class WaybillPayerRenderTest extends TestCase
{
    /** @param array<string, string> $texts переклади замість «[ключ]» */
    private static function render(array $waybill, array $texts = []): string
    {
        $root = dirname(__DIR__, 5);
        // Сток 4.5.3 має Smarty 4 (клас \Smarty), форк — Smarty 5 (\Smarty\Smarty); потрібні методи однакові.
        $class = class_exists('Smarty\\Smarty') ? 'Smarty\\Smarty' : 'Smarty';
        $smarty = new $class();
        $smarty->setTemplateDir([$root . '/Okay/Modules/Sviat/NovaPoshta/Backend/design/html', $root . '/backend/design/html']);
        $smarty->setCompileDir(sys_get_temp_dir() . '/np-waybill-render-' . getmypid());
        // Адмінка не надсилає ключів, яких цей стан не має; Okay\Core\Design так само глушить ці попередження.
        $smarty->muteUndefinedOrNullWarnings();
        $smarty->registerPlugin('function', 'get_design_block', static fn(): string => '');
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

    private static function card(): string
    {
        $row = (object)['number' => '20451581331601', 'ref' => null, 'api' => 'old', 'status_code' => 7, 'tracked_at' => null, 'services' => null];

        return self::render(['orderId' => 990061, 'row' => [
            'number' => '20451581331601', 'numberSpaced' => '20 4515 8133 1601', 'attached' => false, 'deletable' => false,
            'trackingUrl' => 'https://novaposhta.ua/tracking/20451581331601/', 'card' => WaybillCard::fromDocument(W::fixture('status_cod'), $row),
            'services' => [], 'audit' => null,
        ], 'draft' => null, 'refusal' => null]);
    }

    /** Картка має заголовок, як у ROZETKA, Meest і Укрпошти; форма лишає свій. */
    public function testCardHasHeadingLikeOtherCarriers(): void
    {
        self::assertStringContainsString('<div class="np-wb__head np-wb__head--card"><span class="heading_box">[sviat__novaposhta__card_title]</span></div>', self::card());
        self::assertStringNotContainsString('[sviat__novaposhta__card_title]', self::form(W::point()), 'control: the form keeps its own title');
    }

    /** Знак з файла модуля, перед номером; не з сайту перевізника. */
    public function testCardShowsBundledLogoBeforeNumber(): void
    {
        $html = self::card();
        $logo = '<img class="np-wb__logo" src="/Okay/Modules/Sviat/NovaPoshta/Backend/design/images/novaposhta.svg" width="19" height="19" alt="[sviat__novaposhta__logo_alt]">';

        self::assertStringContainsString($logo, $html);
        self::assertLessThan(strpos($html, '20 4515 8133 1601'), strpos($html, $logo));
        self::assertStringNotContainsString('np-wb__logo', self::form(W::point()));
    }

    public function testLogoIsPlainShapes(): void
    {
        $svg = (string)file_get_contents(dirname(__DIR__, 5) . '/Okay/Modules/Sviat/NovaPoshta/Backend/design/images/novaposhta.svg');
        $doc = new \DOMDocument();
        self::assertTrue($doc->loadXML($svg, LIBXML_NONET));
        $tags = [];
        foreach ($doc->getElementsByTagName('*') as $node) {
            $tags[$node->localName] = true;
            foreach ($node->attributes as $attribute) {
                self::assertDoesNotMatchRegularExpression('/^on|href/i', $attribute->name);
                self::assertDoesNotMatchRegularExpression('/url\(|javascript:/i', $attribute->value);
            }
        }
        self::assertSame(['svg', 'path'], array_keys($tags));
    }

    /**
     * Від заголовка до картки — 15px, як у блоку ядра «Параметри замовлення». Обгортка тут flex, тож span
     * заголовка стає блоком і сам дає ці 15px (нижній відступ .heading_box ядра); власний відступ зайвий.
     */
    public function testCardHeadingGapMatchesCore(): void
    {
        $css = (string)file_get_contents(dirname(__DIR__, 5) . '/Okay/Modules/Sviat/NovaPoshta/Backend/design/css/waybill.css');

        self::assertStringNotContainsString('.np-wb__head--card', $css);
        self::assertStringContainsString('.np-wb__head { display: flex;', $css);
    }

    /** Знак у картці заввишки з цифру номера (19px) на його базовій лінії, 8px до номера; viewBox файла обрізано по фарбі. */
    public function testCardLogoMatchesDigitHeight(): void
    {
        $root = dirname(__DIR__, 5) . '/Okay/Modules/Sviat/NovaPoshta/Backend/design';

        self::assertStringStartsWith('<svg viewBox="31.17 34.24 449.66 443.52" ', (string)file_get_contents($root . '/images/novaposhta.svg'));
        self::assertStringContainsString('.np-wb__logo { flex: none; display: block; width: auto; height: 19px; align-self: baseline; margin-right: -4px; }', (string)file_get_contents($root . '/css/waybill.css'));
        self::assertStringContainsString('.np-wb__card .np-wb__number { display: inline-flex; align-items: center; gap: 12px;', (string)file_get_contents($root . '/css/waybill.css'));
        // .fn_clipboard ядра дає номеру 2px зліва: проміжок від знака до цифри був би на 2px більшим, ніж у інших.
        self::assertStringContainsString('.np-wb__card .np-wb__copy { padding-left: 0; align-self: baseline; }', (string)file_get_contents($root . '/css/waybill.css'));
    }

    /**
     * Знак у рядку списку замовлень: viewBox обрізано по фарбі, тож знак починається від лівого краю тексту рядка;
     * 14px фарби й проміжок 5px до номера — як у інших перевізників.
     */
    public function testListLogoInkIs14pxTall(): void
    {
        $root = dirname(__DIR__, 5) . '/Okay/Modules/Sviat/NovaPoshta/Backend/design';
        $css = (string)file_get_contents($root . '/css/waybill.css');

        self::assertStringContainsString('<svg class="np-list-status__logo" width="14" height="14" viewBox="1.41 1.41 21.18 21.18" aria-hidden="true">', (string)file_get_contents($root . '/html/orders_status.tpl'));
        self::assertStringContainsString('.np-list-status__logo { flex: none; display: block; width: auto; height: 14px; }', $css);
        // .fn_clipboard ядра дає номеру 2px зліва, тож проміжок був би 7px замість 5.
        self::assertStringContainsString('.np-list-status .np-list-status__number { padding-left: 0; }', $css);
        // Рядок списку теж у .boxed: без цього svg отримав би 3px з обох боків від ядра.
        self::assertStringContainsString('.np-list-status .np-list-status__logo { margin: 0; }', $css);
    }

    /** Рядок списку бере ту саму підказку, що й картка: «Натисніть, щоб скопіювати» / «Скопійовано». */
    public function testListNumberUsesCardCopyTexts(): void
    {
        $root = dirname(__DIR__, 5) . '/Okay/Modules/Sviat/NovaPoshta/Backend';
        $tpl = (string)file_get_contents($root . '/design/html/orders_status.tpl');

        self::assertStringContainsString('data-hint="{$btr->sviat__novaposhta__card_copy|escape}" data-hint-copied="{$btr->sviat__novaposhta__card_copied|escape}"', $tpl);
        self::assertStringNotContainsString('sviat__novaposhta__list_cop', (string)file_get_contents($root . '/lang/ua.php'));
    }

    /** Значок посилання й бейдж статусу центровані на цифрах номера, а не на його рядку: у Inter 26px/1.2 центр цифр на 1.35px вище. */
    public function testRowItemsCentredOnDigits(): void
    {
        $css = (string)file_get_contents(dirname(__DIR__, 5) . '/Okay/Modules/Sviat/NovaPoshta/Backend/design/css/waybill.css');

        self::assertStringContainsString('.np-wb__card .np-wb__open { transform: translateY(-1.35px); }', $css);
    }
}
