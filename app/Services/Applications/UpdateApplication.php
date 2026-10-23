<?php

namespace App\Services\Applications;

use App\Models\Application;
use App\Models\User;
use App\Services\Audit;
use Illuminate\Support\Facades\DB;

class UpdateApplication
{
    public function handle(Application $application, array $data, User $user): void
    {
        DB::transaction(function () use ($application, $data, $user): void {
            $locked = Application::query()->lockForUpdate()->findOrFail($application->id);
            $before = $locked->only(['status', 'admin_notes']);
            $previous = $locked->status;
            $locked->update(['status' => $data['status'], 'admin_notes' => $data['admin_notes'] ?? null]);
            if ($previous !== $locked->status || ! empty($data['status_note'])) {
                $locked->statusHistory()->create(['user_id' => $user->id, 'from_status' => $previous, 'to_status' => $locked->status, 'notes' => $data['status_note'] ?? null]);
            }
            Audit::record('application.updated', $locked, $before, $locked->only(['status', 'admin_notes']));
        }, 5);
    }
}
