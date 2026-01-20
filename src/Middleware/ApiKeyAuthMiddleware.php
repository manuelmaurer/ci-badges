<?php

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Exception\HttpUnauthorizedException;

final readonly class ApiKeyAuthMiddleware implements MiddlewareInterface
{
    public function __construct(private ?string $apiKey)
    {
    }

    /**
     * @param ServerRequestInterface $request
     * @param RequestHandlerInterface $handler
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $apiKey = $request->getHeaderLine('X-API-KEY');
        if (empty($apiKey) || $apiKey !== $this->apiKey) {
            throw new HttpUnauthorizedException($request, "$apiKey does not match $this->apiKey");
        }
        return $handler->handle($request);
    }
}
