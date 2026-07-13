@php($rows = $getState() ?? [])

@if (filled($rows))
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-gray-500 dark:text-gray-400">
                    <th class="py-1.5 pr-6 font-medium">Field</th>
                    <th class="py-1.5 pr-6 font-medium">From</th>
                    <th class="py-1.5 font-medium">To</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    <tr class="border-t border-gray-200 dark:border-white/10">
                        <td class="py-1.5 pr-6 font-medium text-gray-950 dark:text-white">{{ $row['field'] }}</td>
                        <td class="py-1.5 pr-6 text-gray-500 dark:text-gray-400">{{ $row['old'] }}</td>
                        <td class="py-1.5 text-gray-950 dark:text-white">{{ $row['new'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@else
    <span class="text-sm text-gray-400">&mdash;</span>
@endif
