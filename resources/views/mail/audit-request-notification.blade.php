@php($row = 'padding:6px 12px 6px 0;color:#6b7280')
@php($heading = 'font-size:14px;text-transform:uppercase;letter-spacing:0.08em;color:#6b7280;margin:20px 0 8px 0')
<div style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;max-width:640px;color:#111827">
    <p style="font-size:14px;color:#6b7280;margin:0 0 8px 0">{{ $submission->audit_ref }}</p>
    <h2 style="font-size:20px;margin:0 0 16px 0">{{ $data['companyName'] ?? '' }}</h2>

    <table style="width:100%;border-collapse:collapse;margin-bottom:20px;font-size:14px">
        <tr><td style="{{ $row }};width:140px">Name</td><td style="padding:6px 0"><strong>{{ $data['name'] ?? '' }}</strong></td></tr>
        <tr><td style="{{ $row }}">Email</td><td style="padding:6px 0"><a href="mailto:{{ $submission->email }}">{{ $submission->email }}</a></td></tr>
        <tr><td style="{{ $row }}">Website</td><td style="padding:6px 0"><a href="{{ $data['website'] ?? '' }}">{{ $data['website'] ?? '' }}</a></td></tr>
        <tr><td style="{{ $row }}">Sector</td><td style="padding:6px 0"><strong>{{ $data['sector'] ?? '' }}</strong></td></tr>
        <tr><td style="{{ $row }}">Team size</td><td style="padding:6px 0">{{ $data['teamSize'] ?? '' }}</td></tr>
        <tr><td style="{{ $row }}">Budget</td><td style="padding:6px 0">{{ $data['budgetRange'] ?? '—' }}</td></tr>
        <tr><td style="{{ $row }}">Referrer</td><td style="padding:6px 0">{{ $data['referrer'] ?? '—' }}</td></tr>
    </table>

    <h3 style="{{ $heading }}">Biggest bottleneck</h3>
    <p style="font-size:14px;line-height:1.6;padding:12px;background:#f9fafb;border-left:3px solid #B5522F;margin:0">{{ $data['biggestBottleneck'] ?? '' }}</p>

    @if (! empty($data['sixMonthWin']))
        <h3 style="{{ $heading }}">Six-month win</h3>
        <p style="font-size:14px;line-height:1.6;margin:0">{{ $data['sixMonthWin'] }}</p>
    @endif

    @if (! empty($data['notes']))
        <h3 style="{{ $heading }}">Anything else they said</h3>
        <p style="font-size:14px;line-height:1.6;margin:0">{{ $data['notes'] }}</p>
    @endif

    <h3 style="{{ $heading }}">Sector-specific answers</h3>
    <ul style="font-size:13px;line-height:1.7;padding-left:20px;margin:0">
        @forelse ($sectorAnswers as $key => $value)
            <li><strong>{{ $key }}:</strong> {{ is_array($value) ? implode(', ', $value) : $value }}</li>
        @empty
            <li>None</li>
        @endforelse
    </ul>

    <p style="font-size:12px;color:#9ca3af;margin-top:24px">
        IP: {{ $submission->ip_address }} · UA: {{ $submission->user_agent }}
        · <a href="{{ route('studio.show', $submission) }}" style="color:#9ca3af">Open in the studio</a>
    </p>
</div>
