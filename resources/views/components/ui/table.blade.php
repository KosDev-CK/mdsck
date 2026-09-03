@props(['headers' => [], 'empty' => false, 'emptyTitle' => 'Sin registros', 'emptyDescription' => null])

<div {{ $attributes->class(['overflow-x-auto']) }}>
    <table class="w-full text-sm">
        @if (count($headers))
            <thead>
                <tr class="text-left text-gray-500 border-b border-gray-100 dark:text-gray-400 dark:border-gray-800">
                    @foreach ($headers as $header)
                        <th class="py-2 {{ is_array($header) ? ($header['class'] ?? '') : '' }}">
                            {{ is_array($header) ? ($header['label'] ?? '') : $header }}
                        </th>
                    @endforeach
                </tr>
            </thead>
        @endif

        <tbody>
            @if ($empty)
                <tr>
                    <td colspan="{{ max(count($headers), 1) }}">
                        <x-ui.empty-state :title="$emptyTitle" :description="$emptyDescription" />
                    </td>
                </tr>
            @else
                {{ $slot }}
            @endif
        </tbody>
    </table>
</div>
