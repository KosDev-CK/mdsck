@props(['name' => '', 'size' => 'h-8 w-8 text-sm'])

<span {{ $attributes->class(['inline-flex shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary font-semibold', $size]) }}>
    {{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr(trim($name), 0, 1)) }}
</span>
