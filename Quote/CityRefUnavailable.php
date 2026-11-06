<?php

namespace Okay\Modules\Sviat\NovaPoshta\Quote;

/** Nova Poshta could not be asked now, and no city Ref is known yet: not the same as "there is none". */
final class CityRefUnavailable extends \RuntimeException
{
}
