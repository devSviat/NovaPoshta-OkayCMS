<?php

namespace Okay\Modules\Sviat\NovaPoshta\KeyCheck;

enum KeyAccess: string
{
    case Ok = 'ok';
    case InvalidKey = 'invalid_key';
    /** Ключ дійсний, але цей API його відхиляє (напр. немає доступу до нового API). */
    case Denied = 'denied';
    /** Нова Пошта не відповіла: про ключ нічого не відомо. */
    case Unreachable = 'unreachable';
}
