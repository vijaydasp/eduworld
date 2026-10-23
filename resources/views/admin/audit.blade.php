@extends('layouts.admin')
@section('title','Activity log')
@section('content')
<h1>Activity log</h1><p>Important administrative changes and protected document access.</p><form class="filter-bar" method="get"><label class="sr-only" for="q">Search action</label><input id="q" name="q" value="{{ request('q') }}" placeholder="Search action"><button class="button-secondary">Search</button></form>
<div class="panel table-wrap"><table><thead><tr><th>When</th><th>Team member</th><th>Action</th><th>Record</th><th>Changes</th></tr></thead><tbody>@forelse($logs as $log)<tr><td>{{ $log->created_at->format('d M Y H:i') }}</td><td>{{ $log->user?->name??'System' }}</td><td>{{ $log->action }}</td><td>{{ class_basename($log->subject_type??'') }} #{{ $log->subject_id }}</td><td><details><summary>View</summary><pre>{{ json_encode(['before'=>$log->before,'after'=>$log->after],JSON_PRETTY_PRINT) }}</pre></details></td></tr>@empty<tr><td colspan="5" class="empty">No activity matches this search.</td></tr>@endforelse</tbody></table>{{ $logs->links() }}</div>
@endsection
