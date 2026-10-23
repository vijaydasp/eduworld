<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOfflinePaymentRequest;
use App\Models\Payment;
use App\Services\Payments\PaymentManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PaymentController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:150'],
            'status' => ['nullable', Rule::in(Payment::STATUSES)],
            'channel' => ['nullable', Rule::in(['gateway', 'offline'])],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', Rule::when($request->filled('from'), 'after_or_equal:from')],
        ]);
        $payments = Payment::with('application')
            ->when($filters['q'] ?? null, function ($query, $keyword) {
                $query->where(function ($query) use ($keyword) {
                    foreach (['transaction_reference', 'entered_reference', 'customer_name', 'email', 'phone', 'offline_reference'] as $field) {
                        $query->orWhere($field, 'like', '%'.$keyword.'%');
                    }
                    $query->orWhereHas('application', fn ($application) => $application->where('reference_number', 'like', '%'.$keyword.'%'));
                });
            })
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['channel'] ?? null, fn ($query, $channel) => $query->where('channel', $channel))
            ->when($filters['from'] ?? null, fn ($query, $date) => $query->whereDate('created_at', '>=', $date))
            ->when($filters['to'] ?? null, fn ($query, $date) => $query->whereDate('created_at', '<=', $date))
            ->latest()->paginate(20)->withQueryString();

        return view('admin.payments.index', compact('payments'));
    }

    public function show(Payment $payment): View
    {
        $payment->load('application', 'recordedBy');
        $events = $payment->events()->latest()->paginate(20);

        return view('admin.payments.show', compact('payment', 'events'));
    }

    public function createOffline(): View
    {
        return view('admin.payments.offline');
    }

    public function storeOffline(StoreOfflinePaymentRequest $request, PaymentManager $payments): RedirectResponse
    {
        $payment = $payments->recordOffline($request->validated());

        return to_route('admin.payments.show', $payment)->with('success', 'Offline receipt recorded. This is an administrative record of funds received outside the website.');
    }

    public function link(Request $request, Payment $payment, PaymentManager $payments): RedirectResponse
    {
        $data = $request->validate(['application_reference' => ['required', 'string', 'exists:applications,reference_number']]);
        $payments->link($payment, $data['application_reference']);

        return back()->with('success', 'Payment linked to the application.');
    }

    public function refundOffline(Request $request, Payment $payment, PaymentManager $payments): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:10', 'max:1000'], 'confirmed' => ['accepted']]);
        $payments->refundOffline($payment, $data['reason']);

        return back()->with('success', 'The completed offline refund has been recorded.');
    }
}
