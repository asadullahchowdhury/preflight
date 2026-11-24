<?php

namespace App\GitHub;

use App\Models\Installation;
use App\Models\Repository;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * Keeps installations and their repositories in step with GitHub.
 */
class InstallationSync
{
    public function __construct(private readonly GitHubApp $github) {}

    /**
     * Installations the user can access, according to their own GitHub token. Attaches them to the user
     * and detaches any they lost access to.
     *
     * @return list<Installation>
     */
    public function forUser(User $user): array
    {
        if ($user->github_token === null) {
            return [];
        }

        $installations = [];
        $page = 1;
        do {
            $response = $this->github->asUser($user->github_token)
                ->get('/user/installations', ['per_page' => 100, 'page' => $page])
                ->throw();
            $batch = $response->json('installations');
            $batch = is_array($batch) ? $batch : [];

            foreach ($batch as $data) {
                if (is_array($data)) {
                    $installations[] = $this->upsert($data);
                }
            }
            $page++;
        } while (count($batch) === 100);

        $user->installations()->sync(array_map(static fn (Installation $i) => $i->id, $installations));

        foreach ($installations as $installation) {
            if ($installation->repositories()->doesntExist()) {
                $this->repositories($installation);
            }
        }

        return $installations;
    }

    /**
     * @param  array<mixed>  $data  an installation object from the GitHub API or a webhook
     */
    public function upsert(array $data): Installation
    {
        $account = is_array($data['account'] ?? null) ? $data['account'] : [];

        return Installation::query()->updateOrCreate(
            ['github_id' => (int) Arr::get($data, 'id')],
            [
                'account_id' => (int) Arr::get($account, 'id'),
                'account_login' => (string) Arr::get($account, 'login'),
                'account_type' => (string) Arr::get($account, 'type', 'User'),
                'account_avatar_url' => Arr::get($account, 'avatar_url'),
                'repository_selection' => (string) Arr::get($data, 'repository_selection', 'selected'),
                'suspended_at' => Arr::get($data, 'suspended_at'),
            ],
        );
    }

    /**
     * Replaces the installation's repositories with the ones GitHub lists for it.
     */
    public function repositories(Installation $installation): void
    {
        $seen = [];
        $page = 1;
        do {
            $batch = $this->github->asInstallation($installation->github_id)
                ->get('/installation/repositories', ['per_page' => 100, 'page' => $page])
                ->throw()
                ->json('repositories');
            $batch = is_array($batch) ? $batch : [];

            foreach ($batch as $data) {
                if (is_array($data)) {
                    $seen[] = $this->upsertRepository($installation, $data)->id;
                }
            }
            $page++;
        } while (count($batch) === 100);

        $installation->repositories()->whereNotIn('id', $seen)->delete();
    }

    /**
     * @param  array<mixed>  $data  a repository object (full or the short form in webhooks)
     */
    public function upsertRepository(Installation $installation, array $data): Repository
    {
        return Repository::query()->updateOrCreate(
            ['github_id' => (int) Arr::get($data, 'id')],
            [
                'installation_id' => $installation->id,
                'full_name' => (string) Arr::get($data, 'full_name'),
                'private' => (bool) Arr::get($data, 'private', true),
            ],
        );
    }

    /**
     * @param  list<array<mixed>>  $repositories
     */
    public function removeRepositories(Installation $installation, array $repositories): void
    {
        $ids = array_map(static fn (array $r) => (int) Arr::get($r, 'id'), $repositories);

        DB::transaction(fn () => $installation->repositories()->whereIn('github_id', $ids)->delete());
    }
}
