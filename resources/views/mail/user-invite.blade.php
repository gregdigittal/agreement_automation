<x-mail::message>
# Welcome to CCRS

Dear {{ $userName }},

You have been granted access to the Contract & Compliance Review System with the following role(s): **{{ $roleList }}**.

<x-mail::button :url="$loginUrl">Log In via Azure AD</x-mail::button>

If you have any questions, contact your system administrator.

*CCRS — Contract & Compliance Review System*
</x-mail::message>
