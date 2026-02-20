<x-mail::message>
# {{ $subject }}

Dear {{ $vendorName }},

{{ $body }}

---
<x-mail::button :url="$portalUrl">Visit Vendor Portal</x-mail::button>

*CCRS — Contract & Merchant Agreement Repository System*
</x-mail::message>
