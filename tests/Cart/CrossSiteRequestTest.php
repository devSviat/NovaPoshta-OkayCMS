<?php

namespace Modules\Sviat\NovaPoshta\Cart;

use Okay\Modules\Sviat\NovaPoshta\Cart\CrossSiteRequest;
use PHPUnit\Framework\TestCase;

class CrossSiteRequestTest extends TestCase
{
    public function testTheCartsOwnRequestsPass(): void
    {
        self::assertFalse(CrossSiteRequest::is(['HTTP_SEC_FETCH_SITE' => 'same-origin', 'HTTP_HOST' => 'shop.test']));
        self::assertFalse(CrossSiteRequest::is(['HTTP_SEC_FETCH_SITE' => 'none', 'HTTP_HOST' => 'shop.test']), 'typed into the address bar');
        self::assertFalse(CrossSiteRequest::is(['HTTP_HOST' => 'shop.test']), 'a browser without fetch metadata');
        self::assertFalse(CrossSiteRequest::is(['HTTP_ORIGIN' => 'https://shop.test', 'HTTP_HOST' => 'shop.test']));
    }

    public function testAnotherSiteIsRefused(): void
    {
        self::assertTrue(CrossSiteRequest::is(['HTTP_SEC_FETCH_SITE' => 'cross-site', 'HTTP_HOST' => 'shop.test']));
        self::assertTrue(CrossSiteRequest::is(['HTTP_SEC_FETCH_SITE' => 'same-site', 'HTTP_HOST' => 'shop.test']), 'a sibling subdomain is another site for the cart');
        self::assertTrue(CrossSiteRequest::is(['HTTP_ORIGIN' => 'https://evil.test', 'HTTP_HOST' => 'shop.test']));
        self::assertTrue(CrossSiteRequest::is(['HTTP_ORIGIN' => 'null', 'HTTP_HOST' => 'shop.test']));
    }
}
