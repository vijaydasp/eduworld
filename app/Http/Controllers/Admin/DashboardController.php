<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Application;
use App\Models\Enquiry;
use App\Models\Payment;
use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $metrics = [];
        $recent = collect();
        if ($request->user()->can('view-reports')) {
            $metrics = ['New applications' => Application::where('status', 'new')->count(), 'In progress' => Application::whereNotIn('status', ['completed', 'closed'])->count(),
                'New enquiries' => Enquiry::where('status', 'new')->count(), 'Payments today (BHD)' => number_format((float) Payment::whereDate('paid_at', today())->sum('amount_received'), 3),
                'Pending payments' => Payment::where('status', 'pending')->count(), 'Published news' => Post::published()->count()];
            $recent = Application::latest()->limit(8)->get();
        } else {
            $metrics = ['Published news' => Post::published()->count(), 'Draft articles' => Post::where('is_published', false)->count()];
        }

        return view('admin.dashboard', compact('metrics', 'recent'));
    }

    public function audit(Request $request): View
    {
        $logs = ActivityLog::with('user')->when($request->filled('q'), fn ($q) => $q->where('action', 'like', '%'.$request->string('q').'%'))->latest()->paginate(30)->withQueryString();

        return view('admin.audit', compact('logs'));
    }
}
