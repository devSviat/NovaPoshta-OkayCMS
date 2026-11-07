<?php

namespace Modules\Sviat\NovaPoshta\Waybills;

require_once __DIR__ . '/../Support/WaybillFixtures.php';

use Modules\Sviat\NovaPoshta\Support\WaybillFixtures as W;
use Okay\Modules\Sviat\NovaPoshta\Api\NpApiException;
use Okay\Modules\Sviat\NovaPoshta\Api\NpErrorKind;
use Okay\Modules\Sviat\NovaPoshta\Waybills\WaybillIssues;
use PHPUnit\Framework\TestCase;

class WaybillIssuesTest extends TestCase
{
    private const string KEY = '0123456789abcdef0123456789abcdef';

    public function testNewApiPhoneErrorPointsAtThePhone(): void
    {
        $issue = WaybillIssues::explain(NpApiException::fromNp(NpErrorKind::Rejected, ['errorMessage: RecipientContactName is empty; RecipientsPhone incorrect']));

        self::assertSame('recipient_name', $issue['field']);
        self::assertSame('waybill_advice_recipient_name', $issue['advice']);
        self::assertStringContainsString('RecipientsPhone incorrect', $issue['details']);
    }

    public function testOldApiErrorWithCode(): void
    {
        $answer = W::fixture('old_save_error');
        $issue = WaybillIssues::explain(NpApiException::fromNp(NpErrorKind::Rejected, ['RecipientsPhone invalid format'], ['20000300434']));

        self::assertSame('phone', $issue['field']);
        self::assertSame('waybill_advice_phone', $issue['advice']);
        self::assertSame('RecipientsPhone invalid format', $issue['message']);
        self::assertStringContainsString('20000300434', $issue['details']);
        self::assertContains('20000300434', $answer['errorCodes']);
    }

    public function testPaymentControlWithoutContract(): void
    {
        $issue = WaybillIssues::explain(NpApiException::fromNp(NpErrorKind::Rejected, ['AfterpaymentOnGoodsCost is unavailable']));

        self::assertSame('cod_kind', $issue['field']);
        self::assertSame('waybill_advice_control_unavailable', $issue['advice']);
    }

    public function testAlreadyDeletedAtNovaPoshta(): void
    {
        self::assertSame('waybill_advice_deleted', WaybillIssues::explain(NpApiException::fromNp(NpErrorKind::Rejected, ['shipment_was_deleted']))['advice']);
    }

    public function testKeyProblem(): void
    {
        $issue = WaybillIssues::explain(NpApiException::fromNp(NpErrorKind::InvalidKey, ['API key expired']));

        self::assertNull($issue['field']);
        self::assertSame('waybill_advice_key', $issue['advice']);
    }

    public function testNovaPoshtaDown(): void
    {
        $issue = WaybillIssues::explain(NpApiException::fromNp(NpErrorKind::Unavailable, ['HTTP 502']));

        self::assertNull($issue['field']);
        self::assertSame('waybill_advice_unavailable', $issue['advice']);
    }

    public function testUnknownErrorKeepsItsCode(): void
    {
        $issue = WaybillIssues::explain(NpApiException::fromNp(NpErrorKind::Rejected, ['Something odd'], ['20000999999']));

        self::assertNull($issue['field']);
        self::assertSame('waybill_advice_generic', $issue['advice']);
        self::assertSame('Something odd', $issue['message']);
        self::assertStringContainsString('20000999999', $issue['details']);
    }

    public function testKeyInNovaPoshtaTextIsMasked(): void
    {
        $issue = WaybillIssues::explain(NpApiException::fromNp(NpErrorKind::Rejected, ['API key ' . self::KEY . ' is wrong'], [], [self::KEY]));

        self::assertStringNotContainsString(self::KEY, $issue['details'] . $issue['message']);
    }

    public function testLocalProblemIsAKey(): void
    {
        self::assertSame(['field' => null, 'message' => null, 'advice' => 'waybill_uncertain', 'details' => ''], WaybillIssues::explain('waybill_uncertain'));
    }

    public function testWarnings(): void
    {
        self::assertSame(['waybill_notice_cargo_changed'], WaybillIssues::notices(['CargoType is changed to Cargo']));
        self::assertSame([], WaybillIssues::notices(['Something we do not explain']));
    }

    public function testRefusedEdrpouPointsAtTheCode(): void
    {
        $answer = W::fixture('old_save_org_inactive');
        $issue = WaybillIssues::explain(NpApiException::fromNp(NpErrorKind::Rejected, $answer['errors'], $answer['errorCodes']));

        self::assertSame('edrpou', $issue['field']);
        self::assertSame('waybill_advice_edrpou', $issue['advice']);
        self::assertStringContainsString('EDRPOU', (string)$issue['message']);
    }
}
