<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreContactRequest;
use App\Models\Enquiry;
use App\Models\Setting;
use App\Notifications\NewEnquiry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Notification;
use Illuminate\View\View;
use Throwable;

class ContactController extends Controller
{
    public function create(): View
    {
        return view('contact', ['services' => config('applications.services')]);
    }

    public function store(StoreContactRequest $request): RedirectResponse
    {
        $enquiry = Enquiry::create($request->safe()->except('website'));
        if ($admin = Setting::getValue('application_notification_email') ?: config('applications.admin_email')) {
            try {
                Notification::route('mail', $admin)->notify(new NewEnquiry($enquiry));
            } catch (Throwable $error) {
                report($error);
            }
        }

        return redirect()->route('contact')->with('success', 'Thank you. Your enquiry has been received and our team will contact you.');
    }
}
