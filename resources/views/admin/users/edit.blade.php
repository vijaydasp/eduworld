@extends('layouts.admin')
@section('title',$account->exists?'Edit team member':'Add team member')
@section('content')
<h1>{{ $account->exists?'Edit team member':'Add team member' }}</h1><form class="panel" method="post" action="{{ $account->exists?route('admin.users.update',$account):route('admin.users.store') }}">@csrf @if($account->exists) @method('PUT') @endif
<div class="grid two">
<div class="field"><label for="name">Full name</label><input id="name" name="name" value="{{ old('name',$account->name) }}" required></div>
<div class="field"><label for="email">Email</label><input id="email" name="email" type="email" value="{{ old('email',$account->email) }}" required></div>
<div class="field"><label for="role">Role</label><select id="role" name="role">@foreach(['admin'=>'Admin — applications, enquiries, payments','content_manager'=>'Content Manager — news, gallery, pages','super_admin'=>'Super Admin — full access'] as $value=>$label)<option value="{{ $value }}" @selected(old('role',$account->role)===$value)>{{ $label }}</option>@endforeach</select></div>
<div class="field"><label for="is_active">Access</label><select id="is_active" name="is_active"><option value="1" @selected(old('is_active',$account->is_active))>Active</option><option value="0" @selected(!old('is_active',$account->is_active))>Disabled</option></select></div>
<div class="field"><label for="password">{{ $account->exists?'New password (leave empty to keep)':'Password' }}</label><input id="password" name="password" type="password" autocomplete="new-password" minlength="12"><small>12+ characters, upper and lower case, number and symbol.</small></div>
<div class="field"><label for="password_confirmation">Confirm password</label><input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password"></div>
</div><button class="button">Save team member</button></form>
@endsection
