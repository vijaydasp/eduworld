<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreApplicationRequest;
use App\Models\Application;
use App\Services\Applications\SubmitApplication;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ApplicationController extends Controller
{
    public function create(Request $request): View
    {
        $services = config('applications.services');
        $requestedService = $request->query('service', '');
        $selectedService = is_string($requestedService) && array_key_exists($requestedService, $services) ? $requestedService : 'study_abroad';

        return view('applications.create', compact('services', 'selectedService'));
    }

    public function store(StoreApplicationRequest $request, SubmitApplication $submit): RedirectResponse
    {
        $application = $submit->handle($request->validated());
        $request->session()->put('submitted_application_id', $application->id);

        return redirect()->route('apply.success');
    }

    public function success(Request $request): View
    {
        abort_unless($request->session()->has('submitted_application_id'), 404);
        $application = Application::query()->select('id', 'reference_number', 'service_type')->findOrFail($request->session()->get('submitted_application_id'));

        return view('applications.success', compact('application'));
    }
}
