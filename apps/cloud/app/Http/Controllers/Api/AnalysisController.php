<?php

namespace App\Http\Controllers\Api;

use App\Analysis\AnalysisRunner;
use App\GitHub\OidcVerifier;
use App\Http\Controllers\Controller;
use App\Jobs\PublishAnalysis;
use App\Models\Repository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use UnexpectedValueException;

/**
 * POST /api/analyses: the action sends preflight:check's JSON, authenticated with the job's GitHub OIDC token.
 */
class AnalysisController extends Controller
{
    public function store(Request $request, OidcVerifier $oidc, AnalysisRunner $runner): JsonResponse
    {
        $token = $request->bearerToken();
        if ($token === null) {
            return response()->json(['message' => 'Send the GitHub Actions OIDC token as a Bearer token (permissions: id-token: write).'], 401);
        }

        try {
            $claims = $oidc->verify($token);
        } catch (UnexpectedValueException $e) {
            return response()->json(['message' => 'Invalid OIDC token: '.$e->getMessage()], 401);
        }

        $repository = Repository::query()->with('installation')->where('github_id', (int) $claims['repository_id'])->first();
        if ($repository === null) {
            $name = is_string($claims['repository'] ?? null) ? $claims['repository'] : 'this repository';

            return response()->json(['message' => "The Preflight GitHub App is not installed on {$name}."], 403);
        }
        if (! $repository->installation->isActive()) {
            return response()->json(['message' => 'The Preflight GitHub App installation is suspended.'], 403);
        }

        $payload = $request->validate([
            'server_version' => ['required', 'string', 'max:40'],
            'migrations' => ['present', 'array', 'max:500'],
            'migrations.*' => ['string', 'max:255'],
            'migration_files' => ['sometimes', 'array'],
            'migration_files.*' => ['string', 'max:1024'],
            'notes' => ['sometimes', 'array', 'max:100'],
            'notes.*' => ['string', 'max:2000'],
            'input' => ['required', 'array'],
            'input.statements' => ['present', 'array', 'max:2000'],
            'input.statements.*.sql' => ['required', 'string', 'max:200000'],
            'input.statements.*.migration' => ['nullable', 'string', 'max:255'],
            'input.schema_sql' => ['present', 'nullable', 'string', 'max:5000000'],
            'input.foreign_key_checks' => ['sometimes', 'boolean'],
            'input.default_charset' => ['sometimes', 'string', 'max:40'],
            'context' => ['sometimes', 'array'],
            'context.pull_request' => ['nullable', 'integer'],
            'context.head_sha' => ['nullable', 'string', 'size:40'],
            'context.base_ref' => ['nullable', 'string', 'max:255'],
            'context.workspace' => ['nullable', 'string', 'max:1024'],
        ]);

        try {
            $analysis = $runner->run($repository, $payload, $claims);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        PublishAnalysis::dispatch($analysis);

        return response()->json([
            'id' => $analysis->id,
            'url' => route('analyses.show', $analysis),
            'severity' => $analysis->severity,
            'danger_count' => $analysis->danger_count,
            'warn_count' => $analysis->warn_count,
        ], 201);
    }
}
