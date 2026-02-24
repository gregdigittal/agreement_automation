<div class="space-y-4">
    <h3 class="text-lg font-semibold">Signers</h3>
    <table class="w-full text-sm">
        <thead><tr class="text-left border-b">
            <th class="py-1">Name</th><th>Email</th><th>Status</th><th>Signed At</th>
        </tr></thead>
        <tbody>
            @foreach($session->signers as $signer)
            <tr class="border-b">
                <td class="py-1">{{ $signer->signer_name }}</td>
                <td>{{ $signer->signer_email }}</td>
                <td><span class="px-2 py-0.5 text-xs rounded-full {{ $signer->status === 'signed' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' }}">{{ $signer->status }}</span></td>
                <td>{{ $signer->signed_at?->format('M d, Y H:i') ?? '-' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <h3 class="text-lg font-semibold mt-4">Audit Log</h3>
    <div class="text-sm space-y-1">
        @foreach($session->auditLog as $entry)
        <div class="flex justify-between text-gray-600">
            <span>{{ $entry->event }}</span>
            <span>{{ $entry->created_at->format('M d, Y H:i:s') }}</span>
        </div>
        @endforeach
    </div>
</div>
