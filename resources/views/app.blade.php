<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @class(['dark' => ($appearance ?? 'system') == 'dark'])>

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    {{-- Inline script to detect system dark mode preference and apply it immediately --}}
    <script>
        (function () {
            const appearance = '{{ $appearance ?? 'system' }}';

            if (appearance === 'system') {
                const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

                if (prefersDark) {
                    document.documentElement.classList.add('dark');
                }
            }
        })();

        // Define asset helper function
        window.asset = function (path) {
            return "{{ asset('') }}" + path;
        };
        // Define storage helper function
        window.storage = function (path) {
            return "{{ asset('storage') }}/" + path;
        };
    </script>

    {{-- Inline style to set the HTML background color based on our theme in app.css --}}
    <style>
        html {
            background-color: oklch(1 0 0);
        }

        html.dark {
            background-color: oklch(0.145 0 0);
        }

        /* BeDesk Widget Toggle Transitions */
        body.hide-bedesk #be-chat-iframe {
            transform: translateX(calc(100% + 20px)) !important;
            opacity: 0 !important;
            pointer-events: none !important;
        }
        #be-chat-iframe {
            transition: transform 0.35s cubic-bezier(0.4, 0, 0.2, 1), opacity 0.3s ease-in-out !important;
        }
    </style>

    <title inertia>{{ config('app.name', 'Laravel') }}</title>
    <link rel="icon" type="image/x-icon" href="{{ asset('images/logos/favicon.png') }}">

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />
    <script src="{{ asset('js/jquery.min.js') }}"></script>
    @routes
    @if (app()->environment('local'))
        @viteReactRefresh
    @endif
    @vite([
        'resources/js/app.tsx',
        'resources/css/app.css',
        'resources/css/dark-mode.css',
    ])
    <script>
        // Ensure base URL is correctly set for assets
        window.baseUrl = '{{ url('/') }}';

        // BeDesk Live Chat Configuration
        window.BeChatSettings = {
            widgetDomain: "https://help.ribo.com.ph"
        };

        // Helper: open a help article in our React side panel
        // (populated once React mounts the HelpArticlePanelRoot)
        window.__openHelpArticle = function(path) {
            // Try to call the React panel opener if available
            if (window.__helpArticlePanelOpen) {
                window.__helpArticlePanelOpen(path);
                return;
            }
            // Fallback: open in new tab
            window.open('https://help.ribo.com.ph/hc' + path, '_blank');
        };

        // Patch history.pushState to catch any /hc/* navigations before they reach React Router
        // The BeDesk widget (inside an iframe) may trigger cross-origin navigation attempts,
        // but navigations originating from within this page's JS context can be caught here.
        (function() {
            var _origPushState = history.pushState.bind(history);
            history.pushState = function(state, title, url) {
                if (url && typeof url === 'string' && url.startsWith('/hc')) {
                    var match = url.match(/^\/hc(\/articles\/[^?#]*)/);
                    if (match) {
                        window.__openHelpArticle(match[1]);
                    } else {
                        window.open('https://help.ribo.com.ph/hc' + url.replace(/^\/hc/, ''), '_blank');
                    }
                    return; // Don't actually push the state
                }
                return _origPushState(state, title, url);
            };
        })();

        // Intercept any /hc/* anchor clicks (belt-and-suspenders)
        document.addEventListener('click', function(e) {
            var target = e.target && e.target.closest ? e.target.closest('a') : null;
            if (target && target.href) {
                try {
                    var url = new URL(target.href, window.location.origin);
                    if (url.origin === window.location.origin && url.pathname.startsWith('/hc')) {
                        e.preventDefault();
                        e.stopPropagation();
                        window.__openHelpArticle(url.pathname.replace(/^\/hc/, '') + url.search + url.hash);
                    }
                } catch(err) {}
            }
        }, true);

        // Listen for postMessages from the BeDesk widget iframe that might request navigation
        window.addEventListener('message', function(e) {
            try {
                var data = e.data;
                if (!data || data.source !== 'livechat-widget') return;
                // Catch any navigate-type messages from the widget
                if (data.type === 'navigate' || data.type === 'open' || data.type === 'link') {
                    var href = data.url || data.href || data.link;
                    if (href) {
                        var parsed = new URL(href, window.location.origin);
                        if (parsed.pathname.startsWith('/hc')) {
                            window.__openHelpArticle(parsed.pathname.replace(/^\/hc/, '') + parsed.search);
                        }
                    }
                }
            } catch(err) {}
        });

        // Use the Navigation API (Chrome 102+) to intercept /hc/* navigations at the browser level
        if (window.navigation) {
            window.navigation.addEventListener('navigate', function(e) {
                try {
                    var url = new URL(e.destination.url);
                    if (url.pathname.startsWith('/hc')) {
                        e.preventDefault();
                        var match = url.pathname.match(/^\/hc(\/articles\/[^?#]*)/);
                        if (match) {
                            window.__openHelpArticle(match[1]);
                        } else {
                            window.open('https://help.ribo.com.ph/hc' + url.pathname.replace(/^\/hc/, ''), '_blank');
                        }
                    }
                } catch(err) {}
            });
        }

        // Intercept popstate as last resort (catches browser back/forward to /hc/ paths)
        window.addEventListener('popstate', function() {
            if (window.location.pathname.startsWith('/hc')) {
                var pathname = window.location.pathname;
                history.replaceState(null, '', '/');
                var match = pathname.match(/^\/hc(\/articles\/[^?#]*)/);
                if (match) {
                    window.__openHelpArticle(match[1]);
                }
            }
        });
    </script>
    <script src="https://help.ribo.com.ph/livechat-loader.js"></script>
    @inertiaHead
</head>

<body class="font-sans antialiased">
    @inertia
</body>

</html>