<x-mail::message>
# Vendor Portal Login

Dear {{ $vendorName }},

Click the button below to log in to your CCRS Vendor Portal. This link expires in 48 hours.

<x-mail::button :url="$link">Log In to Portal</x-mail::button>

If you did not request this link, please ignore this email.

*CCRS — Contract & Merchant Agreement Repository System*
</x-mail::message>
