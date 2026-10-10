<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Support\WorkspaceContext;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Writes the admin audit trail. Controllers call it explicitly after an
 * action succeeds, so each entry describes something that really happened.
 *
 * Works without a signed-in user (console, queued jobs): the entry is then
 * attributed to no one. Any property whose key looks like a credential
 * (password, secret, token, ...) is dropped before it is stored, so a careless
 * caller cannot leak one into the log.
 */
class ActivityLogger
{
    /** Property keys that are never stored, at any depth. */
    protected const SECRET_KEY_PATTERN = '/(password|secret|token|credential|api[_-]?key|authorization|remember)/i';

    public function __construct(
        protected Application $app,
        protected AuthFactory $auth,
        protected WorkspaceContext $context,
    ) {}

    /**
     * Record an action. Returns null when there is no workspace to file it
     * under (neither a current workspace nor a subject that belongs to one).
     *
     * @param  array<string, mixed>  $properties
     */
    public function log(string $action, string $description, ?Model $subject = null, array $properties = []): ?ActivityLog
    {
        $workspaceId = $this->context->id() ?? $subject?->getAttribute('workspace_id');

        if ($workspaceId === null) {
            return null;
        }

        $properties = $this->scrub($properties);

        return ActivityLog::create([
            'workspace_id' => $workspaceId,
            'user_id' => $this->auth->guard()->id(),
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'description' => Str::limit($description, 250),
            'properties' => $properties === [] ? null : $properties,
            'ip_address' => $this->app->runningInConsole() && ! $this->app->runningUnitTests() ? null : $this->app['request']->ip(),
        ]);
    }

    /**
     * The attributes that differ between two snapshots, as
     * ['field' => ['old' => ..., 'new' => ...]].
     *
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @return array<string, array{old: mixed, new: mixed}>
     */
    public static function changes(array $before, array $after): array
    {
        $changes = [];

        foreach (array_unique(array_merge(array_keys($before), array_keys($after))) as $key) {
            $old = $before[$key] ?? null;
            $new = $after[$key] ?? null;

            if ($old !== $new) {
                $changes[$key] = ['old' => $old, 'new' => $new];
            }
        }

        return $changes;
    }

    /**
     * Remove credential-like keys, recursively.
     *
     * @param  array<array-key, mixed>  $properties
     * @return array<array-key, mixed>
     */
    protected function scrub(array $properties): array
    {
        $clean = [];

        foreach ($properties as $key => $value) {
            if (is_string($key) && preg_match(self::SECRET_KEY_PATTERN, $key)) {
                continue;
            }

            $clean[$key] = is_array($value) ? $this->scrub($value) : $value;
        }

        return $clean;
    }
}
