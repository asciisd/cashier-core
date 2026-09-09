<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="referrer" content="origin">
    <title>{{ __('Redirecting to payment…') }}</title>
    <style>
        body { font-family: system-ui, -apple-system, "Segoe UI", sans-serif; display: flex;
               align-items: center; justify-content: center; min-height: 100vh; margin: 0;
               color: #1f2933; background: #f5f7fa; }
        .panel { text-align: center; padding: 2rem; }
        button { font: inherit; padding: 0.6rem 1.2rem; cursor: pointer; }
    </style>
</head>
<body>
<div class="panel">
    <p>{{ __('Redirecting you to the secure payment page…') }}</p>

    <form id="xoala-checkout" method="POST" action="{{ $action }}">
        @foreach ($fields as $name => $value)
            <input type="hidden" name="{{ $name }}" value="{{ $value }}">
        @endforeach

        {{-- The flow must still complete where scripts are blocked. --}}
        <noscript>
            <button type="submit">{{ __('Continue to payment') }}</button>
        </noscript>
    </form>
</div>

<script>document.getElementById('xoala-checkout').submit();</script>
</body>
</html>
