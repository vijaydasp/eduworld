@if(config('eduworld.turnstile_site_key'))
<div class="cf-turnstile" data-sitekey="{{ config('eduworld.turnstile_site_key') }}"></div>
<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
@endif
