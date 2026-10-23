<?php

namespace App\Services\Applications;

use App\Models\Application;
use App\Models\Setting;
use App\Notifications\ApplicationReceived;
use App\Notifications\NewApplication;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class SubmitApplication
{
    public function __construct(private ReferenceGenerator $references) {}

    public function handle(array $data): Application
    {
        $stored = [];
        try {
            $application = DB::transaction(function () use ($data, &$stored): Application {
                if ($stored !== []) {
                    Storage::disk('private')->delete($stored);
                    $stored = [];
                }
                $application = Application::create(Arr::only($data, ['service_type', 'full_name', 'nationality', 'date_of_birth', 'current_country', 'current_city', 'phone', 'email', 'preferred_contact_method']) + [
                    'reference_number' => $this->references->next(), 'status' => 'new', 'payment_status' => 'pending',
                    'consent_accuracy' => true, 'consent_processing' => true, 'consented_at' => now(),
                    'remarks' => $data['service_type'] === 'other' ? $data['details']['remarks'] : null,
                ]);
                $relation = config('applications.services.'.$application->service_type.'.relation');
                if ($relation) {
                    $application->{$relation}()->create($data['details']);
                }
                foreach ($data['documents'] ?? [] as $kind => $files) {
                    foreach ($files ?? [] as $file) {
                        $path = $file->store('applications/'.$application->id, 'private');
                        if (! $path) {
                            throw new RuntimeException('The private document could not be stored.');
                        }
                        $stored[] = $path;
                        $originalName = str_replace(['/', '\\', "\r", "\n", "\0"], '_', $file->getClientOriginalName());
                        $application->documents()->create([
                            'document_type' => $kind, 'disk' => 'private', 'path' => $path,
                            'original_name' => Str::limit($originalName ?: 'document', 240, ''),
                            'mime_type' => $file->getMimeType(), 'size' => $file->getSize(),
                        ]);
                    }
                }
                $application->statusHistory()->create(['to_status' => 'new', 'notes' => 'Application submitted online.']);

                return $application;
            }, 5);
        } catch (Throwable $error) {
            Storage::disk('private')->delete($stored);
            throw $error;
        }
        try {
            Notification::route('mail', $application->email)->notify(new ApplicationReceived($application));
            if ($admin = Setting::getValue('application_notification_email') ?: config('applications.admin_email')) {
                Notification::route('mail', $admin)->notify(new NewApplication($application));
            }
        } catch (Throwable $error) {
            report($error);
        }

        return $application;
    }
}
