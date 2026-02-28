<x-mail::message>
# Access Approved

Dear {{ $userName }},

Your access to the Contract & Compliance Review System has been approved. You have been assigned the following role(s): **{{ $roleList }}**.

<x-mail::button :url="$loginUrl">Log In via Azure AD</x-mail::button>

*CCRS — Contract & Compliance Review System*
</x-mail::message>
