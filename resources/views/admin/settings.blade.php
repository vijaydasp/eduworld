@extends('layouts.admin')
@section('title','Website settings')
@section('content')
<div class="page-heading"><div><span class="eyebrow">CONFIGURATION</span><h1>Website settings</h1><p>Update the details customers see across your website.</p></div></div>
<form class="panel" method="post" action="{{ route('admin.settings.update') }}" enctype="multipart/form-data">@csrf @method('PUT')
<div class="grid two">@foreach(config('website') as $key=>$field)<div class="field"><label for="{{ $key }}">{{ $field['label'] }}</label><input id="{{ $key }}" name="{{ $key }}" value="{{ old($key,$settings[$key]??$field['default']) }}" @if($key==='currency') readonly @endif></div>@endforeach
<div class="field"><label for="logo">Custom logo (PNG/JPEG/WebP, max 2 MB)</label><input type="file" id="logo" name="logo" accept="image/png,image/jpeg,image/webp"></div><div class="field"><label for="favicon">Custom favicon (PNG, max 512 KB)</label><input type="file" id="favicon" name="favicon" accept="image/png"></div></div>
<button class="button">Save settings</button></form>
@endsection
