<?php

namespace Okay\Modules\Sviat\NovaPoshta\KeyCheck;

enum KeyAccess: string
{
    case Ok = 'ok';
    case InvalidKey = 'invalid_key';
    /** The key is valid but this API refuses it (e.g. no access to the new API). */
    case Denied = 'denied';
    /** Nova Poshta did not answer: nothing is known about the key. */
    case Unreachable = 'unreachable';
}
