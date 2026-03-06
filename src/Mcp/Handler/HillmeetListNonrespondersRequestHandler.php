<?php

declare(strict_types=1);

namespace Hillmeet\Mcp\Handler;

use Hillmeet\Exception\HillmeetNotFound;
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
 * Handles tools/call for hillmeet_list_nonresponders only.
 * Resolves owner from tenant; validates poll_id and delegates to HillmeetAdapter.
 */
final class HillmeetListNonrespondersRequestHandler implements RequestHandlerInterface
{
    private const TOOL_NAME = 'hillmeet_list_nonresponders';

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

        try {
            $result = $this->adapter->listNonresponders($ownerEmail, $pollId);
        } catch (HillmeetNotFound $e) {
            $durationMs = (int) round((hrtime(true) - $start) / 1e6);
            $this->logAudit($tenant, $durationMs, false, $id, $e->getMessage(), self::CODE_NOT_FOUND);
            return new Error($id, self::CODE_NOT_FOUND, 'Poll not found or access denied');
        } catch (\Throwable $e) {
            $durationMs = (int) round((hrtime(true) - $start) / 1e6);
            $this->logAudit($tenant, $durationMs, false, $id, $e->getMessage(), self::CODE_INTERNAL);
            return new Error($id, self::CODE_INTERNAL, 'Internal error', ['message' => $e->getMessage()]);
        }

        $durationMs = (int) round((hrtime(true) - $start) / 1e6);
        $this->logAudit($tenant, $durationMs, true, $id, null, null);

        $content = [
            'nonresponders' => $result->nonresponders,
            'summary' => $result->summary,
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
}
