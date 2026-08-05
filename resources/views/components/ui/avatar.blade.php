@props(['name' => '', 'size' => 'h-8 w-8 text-sm'])

<span {{ $attributes->class(['inline-flex shrink-0 items-center justify-center rounded-full bg-indigo-100 text-indigo-700 font-semibold dark:bg-indigo-500/20 dark:text-indigo-300', $size]) }}>
    {{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr(trim($name), 0, 1)) }}
</span>
