<?php

declare(strict_types=1);

namespace LabTrack\Tests\Unit;

use LabTrack\Http\Request;
use LabTrack\Http\Response;
use LabTrack\Router\Router;
use LabTrack\View\View;
use PHPUnit\Framework\TestCase;

class RouterAndHttpTest extends TestCase
{
    public function testRouterExtractsNamedParameters(): void
    {
        $router = new Router();
        $capturedTag = null;

        $router->get('/assets/{tag}', function (Request $req, string $tag) use (&$capturedTag) {
            $capturedTag = $tag;
            return new Response('Asset: ' . $tag);
        });

        $req = new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/assets/MON-2026-0042']);
        $resp = $router->dispatch($req);

        $this->assertSame(200, $resp->getStatusCode());
        $this->assertSame('MON-2026-0042', $capturedTag);
        $this->assertSame('Asset: MON-2026-0042', $resp->getContent());
    }

    public function testRouterReturns404ForUnknownRoute(): void
    {
        $router = new Router();
        $req = new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/nonexistent/page']);
        $resp = $router->dispatch($req);

        $this->assertSame(404, $resp->getStatusCode());
    }

    public function testRouterReturns405ForMismatchedMethod(): void
    {
        $router = new Router();
        $router->post('/submit', function () {
            return new Response('OK');
        });

        $req = new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/submit']);
        $resp = $router->dispatch($req);

        $this->assertSame(405, $resp->getStatusCode());
    }

    public function testCsrfTokenValidation(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        $_SESSION['csrf_token'] = 'expected_secret_12345';

        // Valid CSRF
        $validReq = new Request([], ['csrf_token' => 'expected_secret_12345'], ['REQUEST_METHOD' => 'POST']);
        $this->assertTrue($validReq->validateCsrf());

        // Invalid CSRF
        $invalidReq = new Request([], ['csrf_token' => 'wrong_token'], ['REQUEST_METHOD' => 'POST']);
        $this->assertFalse($invalidReq->validateCsrf());

        // Missing CSRF
        $emptyReq = new Request([], [], ['REQUEST_METHOD' => 'POST']);
        $this->assertFalse($emptyReq->validateCsrf());
    }

    public function testViewEscapesHtmlCharacters(): void
    {
        $malicious = '<script>alert("xss")</script>';
        $escaped = View::e($malicious);

        $this->assertStringNotContainsString('<script>', $escaped);
        $this->assertSame('&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;', $escaped);
    }
}
