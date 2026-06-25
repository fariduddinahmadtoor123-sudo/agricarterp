<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Staff Registration — {{ config('agricart.brand.name', 'Agricart ERP') }}</title>
    <link rel="stylesheet" href="{{ asset('css/staff-register.css') }}?v={{ filemtime(public_path('css/staff-register.css')) }}">
    @livewireStyles
</head>
<body class="staff-register-page">
    <main class="staff-register-shell">
        <header class="staff-register-header">
            <h1>Staff Registration</h1>
            <p>Apply to join {{ config('agricart.brand.name', 'Agricart ERP') }}. Your application will be reviewed by an administrator.</p>
        </header>

        @livewire('staff-registration')
    </main>

    @livewireScripts
</body>
</html>
