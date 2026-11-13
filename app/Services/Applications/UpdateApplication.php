<?php

namespace App\Services\Applications;

use App\Models\Application;
use App\Models\User;
use App\Services\Audit;
use Illuminate\Support\Facades\DB;

class UpdateApplication
{
    private const EDITABLE_FIELDS = [
        'full_name', 'nationality', 'date_of_birth', 'current_country', 'current_city',
        'phone', 'email', 'preferred_contact_method', 'status', 'admin_notes',
    ];

    public function handle(Application $application, array $data, User $user): void
    {
        DB::transaction(function () use ($application, $data, $user): void {
            $locked = Application::query()->lockForUpdate()->findOrFail($application->id);
            $before = $locked->only(self::EDITABLE_FIELDS);

            $locked->fill(collect($data)->only(self::EDITABLE_FIELDS)->all());
            $locked->save();

            $previous = $before['status'];
            $note = $data['status_note'] ?? null;

            if ($previous !== $locked->status || ($note !== null && $note !== '')) {
                $locked->statusHistory()->create([
                    'user_id' => $user->id,
                    'from_status' => $previous,
                    'to_status' => $locked->status,
                    'notes' => $note,
                ]);
            }

            $this->updateDetails($locked, $data['details'] ?? []);

            Audit::record('application.updated', $locked, $before, $locked->only(self::EDITABLE_FIELDS));
        }, 5);
    }

    /**
     * Persist the service-specific detail fields without changing the service type.
     *
     * @param  array<string, mixed>  $details
     */
    private function updateDetails(Application $application, array $details): void
    {
        $service = config('applications.services.'.$application->service_type, []);
        $fields = array_keys($service['fields'] ?? []);
        $values = array_intersect_key($details, array_flip($fields));

        if ($values === []) {
            return;
        }

        $relation = $service['relation'] ?? null;

        if ($relation === null) {
            if (array_key_exists('remarks', $values)) {
                $application->update(['remarks' => $values['remarks']]);
            }

            return;
        }

        $application->{$relation}()->updateOrCreate([], $values);
    }
}
