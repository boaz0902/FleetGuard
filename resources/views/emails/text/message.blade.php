{{ $businessName }}

{{ $preheader }}

{{ $heading }}

{{ $intro }}
@foreach ($details as $detail)
{{ $detail['label'] }}: {{ $detail['value'] }}
@endforeach
@if ($action)
{{ $action['label'] }}: {{ $action['url'] }}
@endif
@if ($callout)
{{ $callout['heading'] }}
{{ $callout['text'] }}
@if (! empty($callout['action']))
{{ $callout['action']['label'] }}: {{ $callout['action']['url'] }}
@endif
@endif
@if ($note)
{{ $note }}
@endif

@forelse ($contactLines as $label => $value)
{{ $label }}: {{ $value }}
@empty
Contact information is not currently listed.
@endforelse

This is an automated message. Please do not reply to this email.