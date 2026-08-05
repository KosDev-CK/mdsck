@php
    $branding = \App\Models\SiteSetting::current();
@endphp

<style>
    :root {
        --color-primary: {{ $branding->primary_color }};
        --color-success: {{ $branding->success_color }};
        --color-danger: {{ $branding->danger_color }};
        --color-warning: {{ $branding->warning_color }};
        --color-info: {{ $branding->info_color }};
    }
</style>

@if ($branding->faviconUrl())
    <link rel="icon" href="{{ $branding->faviconUrl() }}">
@endif
