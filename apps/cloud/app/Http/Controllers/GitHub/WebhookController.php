<?php

namespace App\Http\Controllers\GitHub;

use App\GitHub\GitHubApp;
use App\GitHub\InstallationSync;
use App\Http\Controllers\Controller;
use App\Models\Installation;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GitHub App webhooks: installs, uninstalls, suspensions and repository access changes.
 */
class WebhookController extends Controller
{
    public function __invoke(Request $request, InstallationSync $sync, GitHubApp $github): JsonResponse
    {
        $secret = (string) config('preflight.github_app.webhook_secret');
        $signature = (string) $request->header('X-Hub-Signature-256');
        $expected = 'sha256='.hash_hmac('sha256', $request->getContent(), $secret);

        if ($secret === '' || ! hash_equals($expected, $signature)) {
            return response()->json(['message' => 'Invalid signature.'], 401);
        }

        $event = (string) $request->header('X-GitHub-Event');
        $action = (string) $request->input('action');
        $data = $request->input('installation');

        if ($event === 'ping') {
            return response()->json(['message' => 'pong']);
        }

        if ($event === 'github_app_authorization' && $action === 'revoked') {
            User::query()->where('github_id', (int) $request->input('sender.id'))->update(['github_token' => null]);

            return response()->json(['message' => 'Token cleared.']);
        }

        if (! is_array($data)) {
            return response()->json(['message' => 'Ignored.']);
        }

        if ($event === 'installation' && $action === 'deleted') {
            Installation::query()->where('github_id', (int) ($data['id'] ?? 0))->delete();
            $github->forgetInstallationToken((int) ($data['id'] ?? 0));

            return response()->json(['message' => 'Installation removed.']);
        }

        $installation = $sync->upsert($data);

        if ($event === 'installation' && in_array($action, ['created', 'new_permissions_accepted'], true)) {
            $repositories = $request->input('repositories');
            foreach (is_array($repositories) ? $repositories : [] as $repository) {
                if (is_array($repository)) {
                    $sync->upsertRepository($installation, $repository);
                }
            }
        }

        if ($event === 'installation_repositories') {
            foreach ((array) $request->input('repositories_added', []) as $repository) {
                if (is_array($repository)) {
                    $sync->upsertRepository($installation, $repository);
                }
            }
            $removed = array_values(array_filter((array) $request->input('repositories_removed', []), 'is_array'));
            $sync->removeRepositories($installation, $removed);
        }

        return response()->json(['message' => 'OK']);
    }
}
