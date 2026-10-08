{{-- Ported from the internal notification in server.ts. --}}
<div style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;max-width:560px;color:#111827">
    <p><strong>New resource lead</strong></p>
    <ul style="font-size:14px;line-height:1.7">
        <li>Email: {{ $lead->email }}</li>
        <li>Name: {{ $lead->first_name ?: '-' }}</li>
        <li>Company: {{ $lead->company ?: '-' }}</li>
        <li>Sector: {{ $lead->sector ?: '-' }}</li>
        <li>Resource: {{ $resourceTitle }} ({{ $slug }})</li>
        <li>Marketing consent: {{ $lead->marketing_consent ? 'yes' : 'no' }}</li>
        <li>IP: {{ $ip }}</li>
    </ul>
</div>
