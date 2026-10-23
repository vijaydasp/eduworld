<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateApplicationRequest;
use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Services\Applications\UpdateApplication;
use App\Services\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ApplicationController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:150'], 'reference' => ['nullable', 'string', 'max:30'],
            'service' => ['nullable', Rule::in(array_keys(config('applications.services')))],
            'status' => ['nullable', Rule::in(array_keys(config('applications.statuses')))],
            'payment_status' => ['nullable', Rule::in(array_keys(config('applications.payment_statuses')))],
            'country' => ['nullable', 'string', 'max:100'], 'nationality' => ['nullable', 'string', 'max:100'],
            'date_from' => ['nullable', 'date'], 'date_to' => ['nullable', 'date'],
        ]);
        $query = Application::query();
        if (! empty($filters['q'])) {
            $term = '%'.$filters['q'].'%';
            $query->where(function ($query) use ($term): void {
                $query->where('full_name', 'like', $term)->orWhere('reference_number', 'like', $term)->orWhere('email', 'like', $term)->orWhere('phone', 'like', $term);
            });
        }
        foreach (['reference' => 'reference_number', 'service' => 'service_type', 'country' => 'current_country', 'nationality' => 'nationality', 'status' => 'status', 'payment_status' => 'payment_status'] as $filter => $column) {
            if (! empty($filters[$filter])) {
                $query->where($column, $filters[$filter]);
            }
        }
        if (! empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        return view('admin.applications.index', ['applications' => $query->latest()->paginate(20)->withQueryString(), 'filters' => $filters]);
    }

    public function show(Application $application): View
    {
        $relation = config('applications.services.'.$application->service_type.'.relation');
        $application->load(['documents', 'statusHistory.user', 'payments']);
        $details = $relation ? $application->load($relation)->getRelation($relation) : null;

        return view('admin.applications.show', compact('application', 'details'));
    }

    public function update(UpdateApplicationRequest $request, Application $application, UpdateApplication $update): RedirectResponse
    {
        $update->handle($application, $request->validated(), $request->user());

        return back()->with('success', 'Application updated.');
    }

    public function download(ApplicationDocument $document): StreamedResponse
    {
        $disk = Storage::disk($document->disk);
        abort_unless($disk->exists($document->path), 404);
        Audit::record('application.document_downloaded', $document, [], ['application_id' => $document->application_id, 'document_type' => $document->document_type]);

        return $disk->download($document->path, $document->original_name, ['Content-Type' => $document->mime_type, 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store']);
    }
}
