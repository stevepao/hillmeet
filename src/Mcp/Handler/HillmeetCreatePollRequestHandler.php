<?php

declare(strict_types=1);

namespace Hillmeet\Mcp\Handler;

use Hillmeet\Exception\HillmeetConflict;
use Hillmeet\Exception\HillmeetNotFound;
use Hillmeet\Exception\HillmeetValidationError;
use Hillmeet\HillmeetAdapter;
use Hillmeet\Mcp\McpContext;
use Mcp\Schema\Content\TextContent;
use Mcp\Schema\JsonRpc\Error;
use Mcp\Schema\JsonRpc\Request;
use Mcp\Schema\JsonRpc\Response;
use Mcp\Schema\Request\CallToolRequest;
use Mcp\Schema\Result\CallToolResult;
use Mcp\Server\Handler\Request\RequestHandlerInterface;
use Mcp\Server\Session\SessionInterface;

/**
 * Handles tools/call for hillmeet_create_poll only.
 * Project: Hillmeet
 * SPDX-License-Identifier: MIT
 * Copyright (c) 2026 Hillwork, LLC
 */
final class HillmeetCreatePollRequestHandler implements RequestHandlerInterface
{
    private const TOOL_NAME = 'hillmeet_create_poll';

    /** JSON-RPC custom error codes */
    private const CODE_VALIDATION = -32010;
    private const CODE_NOT_FOUND = -32020;
    private const CODE_CONFLICT = -32030;
    private const CODE_INTERNAL = -32050;

    public function __construct(
        private readonly HillmeetAdapter $adapter,
        /** @var \Closure(object, string, int, bool, string|int, ?string): void|null */
        private readonly ?\Closure $auditLogger = null,
    ) {
    }

    public function supports(Request $request): bool
    {
        return $request instanceof CallToolRequest && $request->name === self::TOOL_NAME;
    }

    /**
     * @return Response<CallToolResult>|Error
     */
    public function handle(Request $request, SessionInterface $session): Response|Error
    {
        \assert($request instanceof CallToolRequest);
        $id = $request->getId();
        $arguments = $request->arguments ?? [];
        $start = hrtime(true);

        $tenant = McpContext::getTenant();
        if ($tenant === null || empty($tenant->owner_email ?? '')) {
            return new Error($id, self::CODE_INTERNAL, 'No tenant or owner email in context');
        }
        $ownerEmail = strtolower(trim((string) $tenant->owner_email));

        $validation = $this->validate($arguments);
        if ($validation !== null) {
            $durationMs = (int) round((hrtime(true) - $start) / 1e6);
            $this->logAudit($tenant, $durationMs, false, $id, 'Validation error', self::CODE_VALIDATION);
            return new Error($id, self::CODE_VALIDATION, 'Validation error', $validation);
        }

        $payload = $this->mapPayload($arguments);
        $payload['_tenant_id'] = $tenant->tenant_id;

        try {
            $result = $this->adapter->createPoll($ownerEmail, $payload);
        } catch (HillmeetValidationError $e) {
            $durationMs = (int) round((hrtime(true) - $start) / 1e6);
            $this->logAudit($tenant, $durationMs, false, $id, $e->getMessage(), self::CODE_VALIDATION);
            return new Error($id, self::CODE_VALIDATION, $e->getMessage(), $e->data);
        } catch (HillmeetNotFound $e) {
            $durationMs = (int) round((hrtime(true) - $start) / 1e6);
            $this->logAudit($tenant, $durationMs, false, $id, $e->getMessage(), self::CODE_NOT_FOUND);
            return new Error($id, self::CODE_NOT_FOUND, 'Poll not found');
        } catch (HillmeetConflict $e) {
            $durationMs = (int) round((hrtime(true) - $start) / 1e6);
            $this->logAudit($tenant, $durationMs, false, $id, $e->getMessage(), self::CODE_CONFLICT);
            return new Error($id, self::CODE_CONFLICT, 'Conflict');
        } catch (\Throwable $e) {
            $durationMs = (int) round((hrtime(true) - $start) / 1e6);
            $this->logAudit($tenant, $durationMs, false, $id, $e->getMessage(), self::CODE_INTERNAL);
            return new Error($id, self::CODE_INTERNAL, 'Internal error', ['message' => $e->getMessage()]);
        }

        $durationMs = (int) round((hrtime(true) - $start) / 1e6);
        $this->logAudit($tenant, $durationMs, true, $id, null, null);

        $content = [
            'poll_id' => $result->pollId,
            'share_url' => $result->shareUrl,
            'summary' => $result->summary,
            'timezone' => $result->timezone,
        ];
        $callResult = new CallToolResult(
            [new TextContent(json_encode($content, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES))],
            false,
            $content,
        );
        return new Response($id, $callResult);
    }

    /**
     * @return list<array{field: string, reason: string}>|null null if valid
     */
    private function validate(array $args): ?array
    {
        $errors = [];

        if (empty($args['title']) || !\is_string($args['title']) || trim($args['title']) === '') {
            $errors[] = ['field' => 'title', 'reason' => 'required and non-empty'];
        }

        $duration = $args['duration_minutes'] ?? null;
        if (!\is_int($duration) && !(is_numeric($duration) && (int) $duration == $duration)) {
            $errors[] = ['field' => 'duration_minutes', 'reason' => 'must be an integer'];
        } elseif ((int) $duration < 1) {
            $errors[] = ['field' => 'duration_minutes', 'reason' => 'must be positive'];
        }

        $options = $args['options'] ?? null;
        if (!\is_array($options) || $options === []) {
            $errors[] = ['field' => 'options', 'reason' => 'must be a non-empty array'];
        } else {
            foreach ($options as $i => $opt) {
                if (!\is_array($opt)) {
                    $errors[] = ['field' => "options[{$i}]", 'reason' => 'must be an object with start (end is computed by server)'];
                    continue;
                }
                if (array_key_exists('end', $opt)) {
                    $errors[] = ['field' => "options[{$i}].end", 'reason' => 'not allowed; server computes end from duration_minutes'];
                }
                $start = $opt['start'] ?? null;
                if (!\is_string($start) || $start === '') {
                    $errors[] = ['field' => "options[{$i}].start", 'reason' => 'required ISO8601 string (UTC)'];
                }
            }
        }

        $participants = $args['participants'] ?? null;
        if ($participants !== null && $participants !== []) {
            if (!\is_array($participants)) {
                $errors[] = ['field' => 'participants', 'reason' => 'must be an array'];
            } else {
                foreach ($participants as $i => $p) {
                    $contact = \is_array($p) ? ($p['contact'] ?? null) : null;
                    if ($contact === null || !\is_string($contact)) {
                        $errors[] = ['field' => "participants[{$i}].contact", 'reason' => 'required and must be an email'];
                    } elseif (!self::isValidEmail($contact)) {
                        $errors[] = ['field' => "participants[{$i}].contact", 'reason' => 'invalid email'];
                    }
                }
            }
        }

        return $errors === [] ? null : $errors;
    }

    private function logAudit(object $tenant, int $durationMs, bool $ok, string|int $requestId, ?string $error = null, ?int $errorCode = null): void
    {
        if ($this->auditLogger !== null) {
            ($this->auditLogger)($tenant, self::TOOL_NAME, $durationMs, $ok, $requestId, $error, $errorCode);
        } else {
            \Hillmeet\Mcp\Audit::logToolCall($tenant, self::TOOL_NAME, $durationMs, $ok, $requestId, $error, $errorCode);
        }
    }

    private static function isValidEmail(string $email): bool
    {
        return filter_var(trim($email), FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * @return array{title: string, description?: string|null, timezone?: string, duration_minutes: int, options: list<array{start: string}>, participants: list<array{name?: string, email: string}>, deadline?: string|null, idempotency_key?: string|null}
     */
    private function mapPayload(array $args): array
    {
        $title = isset($args['title']) && \is_string($args['title']) ? trim($args['title']) : '';
        $description = isset($args['description']) && \is_string($args['description']) ? trim($args['description']) : null;
        if ($description === '') {
            $description = null;
        }
        $timezone = isset($args['timezone']) && \is_string($args['timezone']) ? trim($args['timezone']) : null;
        if ($timezone === '') {
            $timezone = null;
        }
        $duration_minutes = (int) ($args['duration_minutes'] ?? 60);
        $options = [];
        foreach ($args['options'] ?? [] as $opt) {
            if (\is_array($opt) && isset($opt['start']) && \is_string($opt['start']) && trim($opt['start']) !== '') {
                $options[] = ['start' => trim($opt['start'])];
            }
        }
        $participants = [];
        foreach ($args['participants'] ?? [] as $p) {
            if (!\is_array($p)) {
                continue;
            }
            $contact = $p['contact'] ?? null;
            if ($contact === null || !\is_string($contact) || !self::isValidEmail($contact)) {
                continue;
            }
            $participants[] = [
                'name' => isset($p['name']) && \is_string($p['name']) ? trim($p['name']) : null,
                'email' => strtolower(trim($contact)),
            ];
        }
        $deadline = isset($args['deadline']) && \is_string($args['deadline']) ? trim($args['deadline']) : null;
        if ($deadline === '') {
            $deadline = null;
        }
        $idempotency_key = isset($args['idempotency_key']) && \is_string($args['idempotency_key']) ? trim($args['idempotency_key']) : null;
        if ($idempotency_key === '') {
            $idempotency_key = null;
        }

        return [
            'title' => $title,
            'description' => $description,
            'duration_minutes' => $duration_minutes,
            'options' => $options,
            'participants' => $participants,
            'deadline' => $deadline,
            'idempotency_key' => $idempotency_key,
        ] + ($timezone !== null ? ['timezone' => $timezone] : []);
    }
}
