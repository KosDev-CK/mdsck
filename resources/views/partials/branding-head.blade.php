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

        /* Sidebar is fixed "chrome" regardless of light/dark mode, same value in both. */
        --sidebar-header-bg: {{ $branding->sidebar_header_color }};
        --sidebar-body-bg: {{ $branding->sidebar_body_color }};

        /* Topbar color is a light-mode brand accent only — dark mode keeps its own neutral below. */
        --topbar-bg: {{ $branding->topbar_color }};
    }

    .dark {
        --topbar-bg: #111827;
    }
</style>

@if ($branding->faviconUrl())
    <link rel="icon" href="{{ $branding->faviconUrl() }}">
@endif
