<?php

namespace Okay\Modules\Sviat\NovaPoshta\Api;

enum NpErrorKind
{
    /** Ключ API неправильний, прострочений або відкликаний. */
    case InvalidKey;
    /** Новий API відхилив токен навіть після видачі нового. */
    case Unauthorized;
    case RateLimited;
    /** Нова Пошта зрозуміла запит і відмовила; див. коди. */
    case Rejected;
    /** Немає відповіді або помилка сервера: про ключ і запит нічого не відомо. */
    case Unavailable;
    case Malformed;
}
