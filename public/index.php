<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/Core/Bootstrap.php';

use Addons\GameSessions\Application\ReplayVerifier;
use Addons\GameSessions\Application\SignedPracticeSessionService;
use JsonException;

function respond(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    exit;
}

function requestPath(): string
{
    $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
    return (string) (parse_url($uri, PHP_URL_PATH) ?: '/');
}

/** @return array<string,mixed> */
function readJsonBody(): array
{
    $contentLength = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
    if ($contentLength > 262144) {
        respond(['error' => 'request_too_large'], 413);
    }

    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
        return [];
    }

    $decoded = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
    if (!is_array($decoded)) {
        respond(['error' => 'json_object_required'], 400);
    }
    return $decoded;
}

$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$path = requestPath();

if ($method === 'GET' && ($path === '/' || $path === '/health')) {
    respond([
        'status' => 'ok',
        'service' => 'bubble-royale',
        'phase' => '21-cash-readiness-gate',
        'cash_mode' => false,
        'features' => [
            'verified_practice' => true,
            'virtual_progression' => true,
            'virtual_tournaments' => true,
            'virtual_leaderboards' => true,
            'virtual_wallet' => true,
            'sandbox_ledger' => true,
            'anti_fraud_review' => true,
            'admin_audit' => true,
            'sandbox_payments' => true,
            'notifications_support' => true,
            'pwa_shell' => true,
            'virtual_alpha' => true,
            'cash_tournaments' => false,
            'compliance_gates' => true,
            'cash_readiness_only' => true,
        ],
    ]);
}

if ($method !== 'POST') {
    respond(['error' => 'method_not_allowed'], 405);
}

$secret = (string) (getenv('BUBBLE_ROYALE_SESSION_SECRET') ?: '');
if (strlen($secret) < 32) {
    respond(['error' => 'session_secret_not_configured'], 503);
}

$sessionService = new SignedPracticeSessionService($secret);

if ($path === '/api/v1/practice/sessions') {
    try {
        $session = $sessionService->issue();
    } catch (Throwable) {
        respond(['error' => 'session_issue_failed'], 500);
    }

    respond([
        'session_token' => $session['token'],
        'session' => $session['claims'],
        'cash_mode' => false,
    ], 201);
}

if ($path === '/api/v1/practice/sessions/verify') {
    try {
        $body = readJsonBody();
    } catch (JsonException) {
        respond(['error' => 'invalid_json'], 400);
    }

    $token = $body['session_token'] ?? null;
    $replay = $body['replay'] ?? null;
    if (!is_string($token) || !is_array($replay)) {
        respond(['error' => 'session_token_and_replay_required'], 400);
    }

    try {
        $claims = $sessionService->verify($token);
    } catch (Throwable) {
        respond(['error' => 'invalid_or_expired_session'], 401);
    }

    $result = (new ReplayVerifier())->verify($replay, $claims);
    if ($result['valid'] !== true) {
        respond([
            'error' => 'replay_rejected',
            'verification' => $result,
            'cash_mode' => false,
        ], 422);
    }

    respond([
        'verified' => true,
        'verification' => $result,
        'cash_mode' => false,
    ]);
}

respond(['error' => 'not_found'], 404);
