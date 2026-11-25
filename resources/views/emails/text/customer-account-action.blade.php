{{ $businessName }}

{{ $preheader }}

{{ $heading }}

{{ $intro }}

@if ($code)
Your verification code: {{ $code }}
@endif

@if ($action)
{{ $action['label'] }}: {{ $action['url'] }}
@endif

@if ($note)
{{ $note }}
@endif
