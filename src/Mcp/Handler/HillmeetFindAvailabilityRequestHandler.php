<?php

declare(strict_types=1);

namespace Hillmeet\Mcp\Handler;

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
 * Handles tools/call for hillmeet_find_availability only.
 * Resolves tenant (owner email from API key); validates arguments and delegates to HillmeetAdapter.
 * Project: Hillmeet
 * SPDX-License-Identifier: MIT
 * Copyright (c) 2026 Hillwork, LLC
 */
final class HillmeetFindAvailabilityRequestHandler implements RequestHandlerInterface
{
    private const TOOL_NAME = 'hillmeet_find_availability';

    private const CODE_VALIDATION = -32010;
    private const CODE_NOT_FOUND = -32020;
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

        $pollId = trim((string) ($arguments['poll_id'] ?? ''));
        $constraints = $this->mapConstraints($arguments);

        try {
            $result = $this->adapter->findAvailability($ownerEmail, $pollId, $constraints);
        } catch (\Throwable $e) {
            $durationMs = (int) round((hrtime(true) - $start) / 1e6);
            $this->logAudit($tenant, $durationMs, false, $id, $e->getMessage(), self::CODE_INTERNAL);
            return new Error($id, self::CODE_INTERNAL, 'Internal error', ['message' => $e->getMessage()]);
        }

        $durationMs = (int) round((hrtime(true) - $start) / 1e6);
        $this->logAudit($tenant, $durationMs, true, $id, null, null);

        $content = [
            'best_slots' => $result->bestSlots,
            'summary' => $result->summary,
            'share_url' => $result->shareUrl,
        ];
        $callResult = new CallToolResult(
            [new TextContent(json_encode($content, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES))],
            false,
            $content,
        );
        return new Response($id, $callResult);
    }

    /**
     * @return list<array{field: string, reason: string}>|null
     */
    private function validate(array $args): ?array
    {
        $errors = [];
        $pollId = $args['poll_id'] ?? null;
        if ($pollId === null || !\is_string($pollId) || trim($pollId) === '') {
            $errors[] = ['field' => 'poll_id', 'reason' => 'required and non-empty string'];
        }
        if (isset($args['min_attendees']) && !\is_int($args['min_attendees']) && !(is_numeric($args['min_attendees']) && (int) $args['min_attendees'] == $args['min_attendees'])) {
            $errors[] = ['field' => 'min_attendees', 'reason' => 'must be an integer'];
        }
        if (isset($args['prefer_times']) && !\is_array($args['prefer_times'])) {
            $errors[] = ['field' => 'prefer_times', 'reason' => 'must be an array of {start, end} objects'];
        } elseif (isset($args['prefer_times']) && \is_array($args['prefer_times'])) {
            foreach ($args['prefer_times'] as $i => $w) {
                if (!\is_array($w) || !isset($w['start'], $w['end']) || !\is_string($w['start']) || !\is_string($w['end'])) {
                    $errors[] = ['field' => "prefer_times[{$i}]", 'reason' => 'must have start and end (ISO8601 UTC strings)'];
                }
            }
        }
        if (isset($args['exclude_emails']) && !\is_array($args['exclude_emails'])) {
            $errors[] = ['field' => 'exclude_emails', 'reason' => 'must be an array of email strings'];
        }
        return $errors === [] ? null : $errors;
    }

    /** @return array{min_attendees?: int, prefer_times?: list<array{start: string, end: string}>, exclude_emails?: list<string>} */
    private function mapConstraints(array $args): array
    {
        $out = [];
        if (isset($args['min_attendees']) && (is_int($args['min_attendees']) || (is_numeric($args['min_attendees']) && (int) $args['min_attendees'] == $args['min_attendees']))) {
            $out['min_attendees'] = max(0, (int) $args['min_attendees']);
        }
        if (isset($args['prefer_times']) && \is_array($args['prefer_times'])) {
            $windows = [];
            foreach ($args['prefer_times'] as $w) {
                if (\is_array($w) && isset($w['start'], $w['end']) && \is_string($w['start']) && \is_string($w['end'])) {
                    $windows[] = ['start' => trim($w['start']), 'end' => trim($w['end'])];
                }
            }
            if ($windows !== []) {
                $out['prefer_times'] = $windows;
            }
        }
        if (isset($args['exclude_emails']) && \is_array($args['exclude_emails'])) {
            $emails = [];
            foreach ($args['exclude_emails'] as $e) {
                if (\is_string($e) && $e !== '') {
                    $emails[] = strtolower(trim($e));
                }
            }
            if ($emails !== []) {
                $out['exclude_emails'] = $emails;
            }
        }
        return $out;
    }

    private function logAudit(object $tenant, int $durationMs, bool $ok, string|int $requestId, ?string $error = null, ?int $errorCode = null): void
    {
        if ($this->auditLogger !== null) {
            ($this->auditLogger)($tenant, self::TOOL_NAME, $durationMs, $ok, $requestId, $error, $errorCode);
        } else {
            \Hillmeet\Mcp\Audit::logToolCall($tenant, self::TOOL_NAME, $durationMs, $ok, $requestId, $error, $errorCode);
        }
    }
}
