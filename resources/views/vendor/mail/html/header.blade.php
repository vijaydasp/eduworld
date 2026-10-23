@props(['url'])
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
{{ \App\Models\Setting::getValue('company_name', 'Edu World') }}
</a>
</td>
</tr>
