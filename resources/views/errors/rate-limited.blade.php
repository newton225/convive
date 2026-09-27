{{--
    Limite de debit atteinte sans page d'origine ou revenir (voir `App\Http\Responses\RateLimitedResponse`).
    Page autonome, sans Vite : elle doit s'afficher meme si le reste de l'interface ne charge pas.
    Styles nonces comme `app.blade.php`, la CSP interdisant tout style en ligne non signe.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('common.rate_limit.title') }}</title>
    <style nonce="{{ Illuminate\Support\Facades\Vite::cspNonce() }}">
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 16px;
            background: oklch(0.965 0.004 60); color: oklch(0.17 0.006 60);
            font-family: ui-sans-serif, system-ui, sans-serif; }
        main { max-width: 28rem; background: #fff; border-radius: 12px; padding: 24px; }
        h1 { font-size: 1.25rem; margin: 0 0 8px; }
        p { margin: 0 0 16px; line-height: 1.5; }
        a { display: inline-block; min-height: 44px; line-height: 44px; padding: 0 16px;
            border-radius: 8px; background: oklch(0.52 0.13 262); color: #fff; text-decoration: none; }
        a:focus-visible { outline: 2px solid oklch(0.17 0.006 60); outline-offset: 2px; }
    </style>
</head>
<body>
    <main>
        <h1>{{ __('common.rate_limit.title') }}</h1>
        <p>{{ $message }}</p>
        <a href="{{ url('/') }}">{{ __('common.rate_limit.back') }}</a>
    </main>
</body>
</html>
