<?php

namespace Okay\Modules\Sviat\NovaPoshta\Legacy;

/** Nova Poshta did not answer: the row waits for the next tick instead of being reported as not found. */
final class LegacyPlacesUnavailable extends \RuntimeException
{
}
