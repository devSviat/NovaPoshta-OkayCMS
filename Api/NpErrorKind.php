<?php

namespace Okay\Modules\Sviat\NovaPoshta\Api;

enum NpErrorKind
{
    /** The API key is wrong, expired or revoked. */
    case InvalidKey;
    /** The new API refused the token even after a fresh one was issued. */
    case Unauthorized;
    case RateLimited;
    /** Nova Poshta understood the request and refused it; see the codes. */
    case Rejected;
    /** No answer or a server error: nothing is known about the key or the request. */
    case Unavailable;
    case Malformed;
}
