<?php

namespace Modules\Sviat\NovaPoshta\Waybills;

require_once __DIR__ . '/../Support/WaybillFixtures.php';

use Modules\Sviat\NovaPoshta\Support\WaybillFixtures as W;
use Okay\Modules\Sviat\NovaPoshta\Quote\CodKind;
use Okay\Modules\Sviat\NovaPoshta\Waybills\AuditResult;
use Okay\Modules\Sviat\NovaPoshta\Waybills\WaybillAudit;
use PHPUnit\Framework\TestCase;

class WaybillAuditTest extends TestCase
{
    private static function codPoint(array $override = []): \Okay\Modules\Sviat\NovaPoshta\Waybills\WaybillDraft
    {
        return W::point($override + ['cod' => true, 'codAmount' => 500.0, 'codPayer' => 'Sender', 'settlementName' => 'місто Київ']);
    }

    private static function kinds(AuditResult $audit): array
    {
        return array_column($audit->findings, 'kind');
    }

    public function testCodVerified(): void
    {
        $audit = WaybillAudit::compare(self::codPoint(), W::fixture('status_cod'));

        self::assertSame('verified', $audit->status);
        self::assertSame([], $audit->findings);
    }

    /** Протягом 10–20 s після створення Нова Пошта повідомляє платника за замовчуванням, Sender (live 09.11.2026). */
    public function testRecipientPayerIsNotYetReported(): void
    {
        $audit = WaybillAudit::compare(self::codPoint(['codPayer' => 'Recipient']), W::fixture('status_cod'));

        self::assertSame('verified', $audit->status);
        self::assertSame([], $audit->findings);
        self::assertSame(['cod_payer'], $audit->unchecked);
    }

    public function testCodPayerChanged(): void
    {
        $audit = WaybillAudit::compare(self::codPoint(), ['RedeliveryPayer' => 'Recipient'] + W::fixture('status_cod'));

        self::assertSame('mismatch', $audit->status);
        self::assertSame(['cod_payer'], self::kinds($audit));
        self::assertSame(['kind' => 'cod_payer', 'expected' => 'Sender', 'actual' => 'Recipient'], $audit->findings[0]);
    }

    public function testCodAmountChanged(): void
    {
        $audit = WaybillAudit::compare(self::codPoint(['codAmount' => 600.0]), W::fixture('status_cod'));

        self::assertSame(['cod'], self::kinds($audit));
    }

    public function testControlDropped(): void
    {
        $audit = WaybillAudit::compare(self::codPoint(['codKind' => CodKind::Control]), W::fixture('status_control_dropped'));

        self::assertSame('mismatch', $audit->status);
        self::assertSame(['control'], self::kinds($audit));
    }

    public function testUnorderedCodIsAFinding(): void
    {
        self::assertSame(['cod'], self::kinds(WaybillAudit::compare(W::point(), W::fixture('status_cod'))));
    }

    public function testOtherPointWithSameNumber(): void
    {
        $status = ['WarehouseRecipient' => 'Поштомат №1: вул. Інша, 1'] + W::fixture('status_cod');

        self::assertSame(['point'], self::kinds(WaybillAudit::compare(self::codPoint(), $status)));
    }

    public function testOtherCity(): void
    {
        self::assertSame(['city'], self::kinds(WaybillAudit::compare(self::codPoint(), ['CityRecipient' => 'Бровари'] + W::fixture('status_cod'))));
    }

    public function testAddressStreetAndHouse(): void
    {
        $draft = W::address(['street' => 'вул. Хрещатик', 'house' => '22']);

        self::assertSame('verified', WaybillAudit::compare($draft, W::fixture('status_address'))->status);
        self::assertSame('verified', WaybillAudit::compare($draft, W::fixture('status_address_text'))->status);
        self::assertSame(['address'], self::kinds(WaybillAudit::compare($draft->with(['house' => '24']), W::fixture('status_address'))));
        self::assertSame(['address'], self::kinds(WaybillAudit::compare($draft->with(['street' => 'вул. Городоцька']), W::fixture('status_address'))));
        self::assertSame(['service_type'], self::kinds(WaybillAudit::compare(W::point(), W::fixture('status_address'))));
    }

    public function testLiftingCannotBeReadBack(): void
    {
        $audit = WaybillAudit::compare(W::address(['lifting' => true, 'floor' => 5]), W::fixture('status_address'));

        self::assertSame('verified', $audit->status);
        self::assertSame(['lifting'], $audit->unchecked);
    }

    public function testPointNotYetKnownRightAfterTheNewApiIsUnchecked(): void
    {
        $audit = WaybillAudit::compare(W::point(['settlementName' => 'місто Київ']), W::fixture('status_new_documents'));

        self::assertSame('verified', $audit->status);
        self::assertSame(['point'], $audit->unchecked);
    }

    public function testHiddenFieldsAreUnverified(): void
    {
        $audit = WaybillAudit::compare(self::codPoint(), W::fixture('status_hidden'));

        self::assertSame('unverified', $audit->status);
        self::assertSame([], $audit->findings);
    }

    public function testEmptyStatus(): void
    {
        self::assertSame('unverified', WaybillAudit::compare(W::point(), [])->status);
        self::assertSame('unverified', WaybillAudit::compare(W::point(), W::fixture('status_unknown')['data'][0] ?? W::fixture('status_unknown'))->status);
    }

    public function testRoundTrip(): void
    {
        $audit = WaybillAudit::compare(self::codPoint(['codPayer' => 'Recipient']), W::fixture('status_cod'));

        self::assertEquals($audit, AuditResult::fromArray($audit->toArray()));
    }
}
