{{-- Internal notification for a high-fit diagnostic. Ported from server.ts. --}}
<div style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;max-width:560px;color:#111827">
    <p><strong>High-fit diagnostic submission</strong></p>
    <ul style="font-size:14px;line-height:1.7">
        <li>Business type: {{ $answers['businessType'] ?? '' }}</li>
        <li>Task: {{ $answers['task'] ?? '' }}</li>
        <li>Hours/wk: {{ $answers['hours'] ?? '' }}</li>
        <li>Stack: {{ filled($answers['stack'] ?? null) ? $answers['stack'] : 'none given' }}</li>
        <li>Priority: {{ $answers['priority'] ?? '' }}</li>
        <li>Email: {{ filled($answers['email'] ?? null) ? $answers['email'] : 'none given' }}</li>
        <li>Score: {{ $score }} / Tier: {{ $tier }}</li>
    </ul>
</div>
