@props(['url'])
<tr>
<td class="brand-bar">&nbsp;</td>
</tr>
<tr>
<td class="header" align="center">
<a href="{{ $url }}" class="brand-link">
<img src="{{ rtrim((string) config('app.url'), '/') }}/icons/family-money-192.png" class="logo" alt="Family Money" width="72" height="72">
<span class="brand-name">Family Money</span>
</a>
</td>
</tr>
