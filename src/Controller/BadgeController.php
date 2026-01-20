<?php

namespace App\Controller;

use DirectoryIterator;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use JsonException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Respect\Validation\Validatable;
use Slim\Exception\HttpBadRequestException;
use Slim\Exception\HttpInternalServerErrorException;
use Slim\Exception\HttpNotFoundException;
use Respect\Validation\Validator as v;

readonly class BadgeController
{
    private const COLOR_LVL_ORANGE = 50;
    private const COLOR_LVL_YELLOW = 70;
    private const COLOR_LVL_YELLOWGREEN = 80;
    private const COLOR_LVL_GREEN = 90;
    /** @var array<string, string> */
    private array $badges;
    private Validatable $singleBadgeValidator;
    public function __construct()
    {
        // load badges to array
        $dir = new DirectoryIterator(__DIR__ . '/../../badges/');
        $badges = [];
        foreach ($dir as $fileinfo) {
            if (
                !$fileinfo->isFile() ||
                $fileinfo->getExtension() !== 'svg' ||
                ($filePath = $fileinfo->getRealPath()) === false
            ) {
                continue;
            }
            $badges[$fileinfo->getBasename('.svg')] = $filePath;
        }
        $this->badges = $badges;
        // prepare validator
        $valueValidator = v::oneOf(
            v::floatVal()->between(0, 100),
            v::arrayType()
                ->key('text', v::stringType()->notEmpty())
                ->key('color', v::stringType()->notEmpty())
        );
        $this->singleBadgeValidator = v::arrayType()
            ->key('label', v::stringType()->notEmpty())
            ->key('value', $valueValidator);
    }

    /**
     * @param string $badgeName
     * @return string|null
     */
    private function findBadgePath(string $badgeName): ?string
    {
        return $this->badges[md5($badgeName)] ?? null;
    }

    /**
     * @param string $badgeName
     * @return string
     */
    private function createBadgePath(string $badgeName): string
    {
        $hash = md5($badgeName);
        return __DIR__ . "/../../badges/$hash.svg";
    }

    /**
     * Generate a badge SVG from shields.io
     * @param ClientInterface $client
     * @param string $label
     * @param string $value
     * @param string $color
     * @return string
     * @throws GuzzleException
     */
    private function generateBadge(ClientInterface $client, string $label, string $value, string $color): string
    {
        $uri = '/badge/' . urlencode("$label-$value-$color") . '.svg';
        $rsp = $client->request('GET', $uri);
        $rsp->getBody()->rewind();
        return $rsp->getBody()->getContents();
    }

    /**
     * @param ClientInterface $client
     * @param string $label
     * @param float $value
     * @return string
     * @throws GuzzleException
     */
    private function generateGradientBadge(ClientInterface $client, string $label, float $value): string
    {
        $color = $this->colorMap($value);
        return $this->generateBadge($client, $label, "$value%", $color);
    }

    /**
     * Map a percentage to a color
     * @param float $percentage
     * @return string
     */
    private function colorMap(float $percentage): string
    {
        return match (true) {
            $percentage > self::COLOR_LVL_GREEN => 'brightgreen',
            $percentage > self::COLOR_LVL_YELLOWGREEN => 'yellowgreen',
            $percentage > self::COLOR_LVL_YELLOW => 'yellow',
            $percentage > self::COLOR_LVL_ORANGE => 'orange',
            default => 'red',
        };
    }

    /**
     * Download a badge
     * @param Request $request
     * @param Response $response
     * @param string $badgeName
     * @return Response
     */
    public function badgeGet(Request $request, Response $response, string $badgeName): Response
    {
        $badge = $this->findBadgePath($badgeName);
        if ($badge === null || !is_readable($badge)) {
            throw new HttpNotFoundException($request);
        }
        $response->getBody()->write(file_get_contents($badge) ?: '');
        return $response
            ->withHeader('Content-Type', 'image/svg+xml')
            ->withHeader('Cache-Control', 'max-age=3600')
            ->withHeader('Content-Length', (string) filesize($badge));
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param ClientInterface $client
     * @param string $badgeName
     * @return Response
     * @throws GuzzleException
     * @throws JsonException
     */
    public function updateBadge(Request $request, Response $response, ClientInterface $client, string $badgeName): Response
    {
        $body = (array) $request->getParsedBody();
        if (!$this->singleBadgeValidator->validate($body)) {
            throw new HttpBadRequestException($request, 'Invalid request body', $this->singleBadgeValidator->reportError($body));
        }
        if (is_array($body['value'] ?? null)) {
            $badge = $this->generateBadge($client, $body['label'], $body['value']['text'], $body['value']['color']);
        } else {
            $badge = $this->generateGradientBadge($client, $body['label'], $body['value']);
        }
        $badgePath = $this->createBadgePath($badgeName);
        file_put_contents($badgePath, $badge);
        $response->getBody()->write(json_encode(
            ['status' => 'success', 'location' => "/badges/$badgeName"],
            JSON_THROW_ON_ERROR
        ));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Location', "/badges/$badgeName")
            ->withStatus(200);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param ClientInterface $client
     * @return Response
     * @throws GuzzleException
     * @throws JsonException
     */
    public function coverageReport(Request $request, Response $response, ClientInterface $client): Response
    {
        $body = (array) $request->getParsedBody();
        $validator = v::arrayType()
            ->key('name', v::stringType()->notEmpty())
            ->key('reports', v::arrayType()->each($this->singleBadgeValidator));
        if (!$validator->validate($body)) {
            throw new HttpBadRequestException($request, 'Invalid request body', $validator->reportError($body));
        }
        $result = [];
        foreach ($body['reports'] as $item) {
            if (is_array($item['value'] ?? null)) {
                $badge = $this->generateBadge($client, $item['label'], $item['value']['text'], $item['value']['color']);
            } else {
                $badge = $this->generateGradientBadge($client, $item['label'], $item['value']);
            }
            $badgeName = "{$body['name']}-{$item['label']}";
            $badgePath = $this->createBadgePath($badgeName);
            file_put_contents($badgePath, $badge);
            $result[$item['label']] = $badgeName;
        }
        $response->getBody()->write(json_encode($result, JSON_THROW_ON_ERROR));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param string $badgeName
     * @return Response
     */
    public function deleteBadge(Request $request, Response $response, string $badgeName): Response
    {
        $badgePath = $this->createBadgePath($badgeName);
        if (is_readable($badgePath)) {
            unlink($badgePath);
        }
        if (is_file($badgePath)) {
            throw new HttpInternalServerErrorException($request, 'Unable to delete badge');
        }
        return $response->withStatus(204);
    }
}
