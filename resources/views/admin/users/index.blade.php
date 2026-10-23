@extends('layouts.admin')
@section('title','Team access')
@section('content')
<div class="page-heading"><div><span class="eyebrow">ACCESS CONTROL</span><h1>Your team</h1></div><a class="button" href="{{ route('admin.users.create') }}">Add team member</a></div>
<div class="panel table-wrap"><table><thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Access</th><th></th></tr></thead><tbody>@foreach($users as $account)<tr><td>{{ $account->name }}</td><td>{{ $account->email }}</td><td>{{ ucwords(str_replace('_',' ',$account->role)) }}</td><td>{{ $account->is_active?'Active':'Disabled' }}</td><td><a href="{{ route('admin.users.edit',$account) }}">Edit →</a></td></tr>@endforeach</tbody></table>{{ $users->links() }}</div>
@endsection
