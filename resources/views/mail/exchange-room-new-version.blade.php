<x-mail::message>
# New Document Version Uploaded

A new document version has been uploaded to the exchange room for **{{ $contractTitle }}**.

| Detail | Value |
|:-------|:------|
| **Uploaded by** | {{ $authorName }} ({{ $actorSide }}) |
| **File** | {{ $fileName }} |
| **Version** | v{{ $versionNumber }} |

---
<x-mail::button :url="$portalUrl">Open Portal</x-mail::button>

*CCRS — Contract & Merchant Agreement Repository System*
</x-mail::message>
