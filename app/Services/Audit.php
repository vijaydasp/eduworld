<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;

class Audit
{
    public static function record(string $action, Model $subject, array $before = [], array $after = []): void
    {
        $redact = fn (array $data) => array_diff_key($data, array_flip(['password', 'remember_token', 'token', 'secret', 'api_key']));
        ActivityLog::create(['user_id' => auth()->id(), 'action' => $action, 'subject_type' => $subject->getMorphClass(), 'subject_id' => $subject->getKey(), 'before' => $redact($before), 'after' => $redact($after)]);
    }
}
