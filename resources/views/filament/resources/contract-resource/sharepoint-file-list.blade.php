<div class="p-4">
    <table class="w-full text-sm">
        <thead>
            <tr class="border-b border-gray-200 dark:border-gray-700">
                <th class="text-left py-2 px-3 font-medium text-gray-600 dark:text-gray-400">Name</th>
                <th class="text-left py-2 px-3 font-medium text-gray-600 dark:text-gray-400">Size</th>
                <th class="text-left py-2 px-3 font-medium text-gray-600 dark:text-gray-400">Last Modified</th>
                <th class="text-left py-2 px-3 font-medium text-gray-600 dark:text-gray-400">Link</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($items as $item)
                <tr class="border-b border-gray-100 dark:border-gray-800">
                    <td class="py-2 px-3">
                        @if ($item['is_folder'])
                            <span class="inline-flex items-center gap-1">
                                <x-heroicon-o-folder class="w-4 h-4 text-yellow-500" />
                                {{ $item['name'] }}
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1">
                                <x-heroicon-o-document class="w-4 h-4 text-gray-400" />
                                {{ $item['name'] }}
                            </span>
                        @endif
                    </td>
                    <td class="py-2 px-3 text-gray-500">
                        {{ $item['is_folder'] ? '—' : Number::fileSize($item['size']) }}
                    </td>
                    <td class="py-2 px-3 text-gray-500">
                        {{ $item['last_modified'] ? \Carbon\Carbon::parse($item['last_modified'])->diffForHumans() : '—' }}
                    </td>
                    <td class="py-2 px-3">
                        <a href="{{ $item['web_url'] }}" target="_blank" class="text-primary-600 hover:underline">
                            Open
                        </a>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
