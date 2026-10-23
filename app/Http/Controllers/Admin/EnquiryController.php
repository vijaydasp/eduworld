<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateEnquiryRequest;
use App\Models\Enquiry;
use App\Services\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class EnquiryController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:150'], 'status' => ['nullable', Rule::in(['new', 'contacted', 'resolved'])], 'service' => ['nullable', Rule::in(array_keys(config('applications.services')))]]);
        $query = Enquiry::query();
        if (! empty($filters['q'])) {
            $query->where(function ($query) use ($filters): void {
                foreach (['full_name', 'email', 'phone', 'message'] as $field) {
                    $query->orWhere($field, 'like', '%'.$filters['q'].'%');
                }
            });
        }
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['service'])) {
            $query->where('service_type', $filters['service']);
        }

        return view('admin.enquiries.index', ['enquiries' => $query->latest()->paginate(20)->withQueryString(), 'filters' => $filters]);
    }

    public function show(Enquiry $enquiry): View
    {
        return view('admin.enquiries.show', compact('enquiry'));
    }

    public function update(UpdateEnquiryRequest $request, Enquiry $enquiry): RedirectResponse
    {
        DB::transaction(function () use ($request, $enquiry): void {
            $enquiry = Enquiry::query()->lockForUpdate()->findOrFail($enquiry->id);
            $before = $enquiry->only(['status', 'admin_notes']);
            $data = $request->validated();
            if (in_array($data['status'], ['contacted', 'resolved']) && ! $enquiry->contacted_at) {
                $data['contacted_at'] = now();
            }
            $data['resolved_at'] = $data['status'] === 'resolved' ? ($enquiry->resolved_at ?? now()) : null;
            $enquiry->update($data);
            Audit::record('enquiry.updated', $enquiry, $before, $enquiry->only(['status', 'admin_notes']));
        }, 5);

        return back()->with('success', 'Enquiry updated.');
    }
}
