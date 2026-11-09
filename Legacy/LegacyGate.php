<?php

namespace Okay\Modules\Sviat\NovaPoshta\Legacy;

/** Чи можна запустити перенесення зараз; запуск до "Update" чи до довідників лишає шкоду, яку повторний запуск не виправить. */
interface LegacyGate
{
    /** @return string|null мовний ключ того, чого бракує; null, коли все готово */
    public function problem(): ?string;
}
