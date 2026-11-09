<?php

namespace Modules\Sviat\NovaPoshta\Support;

use Okay\Modules\Sviat\NovaPoshta\Api\Http\HttpRequest;
use Okay\Modules\Sviat\NovaPoshta\Api\Http\HttpResponse;
use Okay\Modules\Sviat\NovaPoshta\Api\Http\HttpTransport;
use Okay\Modules\Sviat\NovaPoshta\Api\Http\TransportException;

/** Відповідає з підготовленої черги й записує кожен запит, тож жоден тест не звертається до Нової Пошти. */
final class FakeTransport implements HttpTransport
{
    /** @var list<HttpResponse|TransportException> */
    private array $queue = [];

    /** @var list<HttpRequest> */
    private array $requests = [];

    public function push(HttpResponse|TransportException ...$answers): self
    {
        array_push($this->queue, ...$answers);

        return $this;
    }

    public function json(int $status, array $body): self
    {
        return $this->push(new HttpResponse($status, json_encode($body, JSON_UNESCAPED_UNICODE)));
    }

    public function send(HttpRequest $request): HttpResponse
    {
        $this->requests[] = $request;
        $answer = array_shift($this->queue) ?? throw new \LogicException("Unexpected request: {$request->method} {$request->url}");
        if ($answer instanceof TransportException) {
            throw $answer;
        }

        return $answer;
    }

    /** @return list<HttpRequest> */
    public function requests(): array
    {
        return $this->requests;
    }
}
