<?php

namespace Okay\Modules\Sviat\NovaPoshta\Legacy\Steps;

use Okay\Modules\Sviat\NovaPoshta\Legacy\LegacyBudget;
use Okay\Modules\Sviat\NovaPoshta\Legacy\LegacyData;
use Okay\Modules\Sviat\NovaPoshta\Legacy\LegacyReport;
use Okay\Modules\Sviat\NovaPoshta\Legacy\LegacyStep;
use Okay\Modules\Sviat\NovaPoshta\Legacy\LegacyTargets;
use Okay\Modules\Sviat\NovaPoshta\Payments\CodPayments;
use Okay\Modules\Sviat\NovaPoshta\Quote\CodKind;

/** Прапорець післяплати NovaposhtaCost; вид визначає перемикач "контроль оплати" в NovaPoshtaTracking. */
final class PaymentsStep implements LegacyStep
{
    public const string NAME = 'payments';

    public function __construct(private readonly LegacyData $old, private readonly LegacyTargets $ours)
    {
    }

    public function name(): string
    {
        return self::NAME;
    }

    public function run(int $cursor, LegacyBudget $budget, LegacyReport $report): int
    {
        $kind = $this->old->setting('novapost_payment_control') === '1' ? CodKind::Control : CodKind::Money;
        foreach ($this->old->codPaymentIds() as $id) {
            $payment = $this->ours->payment($id);
            if ($payment === null) {
                continue;
            }
            if ((int)$payment->cod !== 0) {
                $report->add(self::NAME, 'existed');
            } elseif (!CodPayments::isManual($payment->module ?? null)) {
                $report->fail(self::NAME, null, 'legacy_fail_online_payment', (string)($payment->name ?? $id));
            } else {
                $this->ours->setPaymentCod($id, $kind->flag());
                $report->add(self::NAME, 'done');
            }
        }

        return self::DONE;
    }
}
