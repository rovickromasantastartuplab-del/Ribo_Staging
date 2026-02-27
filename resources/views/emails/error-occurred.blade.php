<x-mail::message>
    # An Application Error Occurred

    **URL:** {{ $url }}

    **User:** {{ $userName }}
    **Message:**
    {{ $errorMessage }}

    **Stack Trace Snippet:**
    ```text
    {{ $stackTrace }}
    ```

    Thanks,<br>
    {{ config('app.name') }}
</x-mail::message>