<?php

namespace Okay\Modules\Sviat\NovaPoshta\Quote;

/** Нову Пошту зараз не вдалося запитати, і Ref міста ще не відомий: це не те саме, що "його немає". */
final class CityRefUnavailable extends \RuntimeException
{
}
