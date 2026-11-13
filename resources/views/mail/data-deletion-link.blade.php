{{-- Sent from the privacy page's "delete your data" form. --}}
<div style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;max-width:560px;margin:0 auto;color:#111827">
    <p style="font-size:15px;line-height:1.6">You asked to delete your data from chrisgarlick.com. @if ($submission) Open the link to see what I hold for {{ $submission->audit_ref }} and confirm. @else Open the link to see the {{ $messages === 1 ? 'message' : $messages.' messages' }} you sent me through the site and confirm. @endif</p>
    <p style="margin:24px 0">
        <a href="{{ $url }}" style="display:inline-block;background:#1a1715;color:#ffffff;padding:12px 20px;border-radius:3px;text-decoration:none;font-size:14px;font-weight:500">Delete my data</a>
    </p>
    <p style="font-size:13px;color:#6b7280;line-height:1.6">The link works for {{ $hours }} hours. If you didn't ask for this, ignore this email and nothing will change.</p>
    <p style="font-size:13px;color:#6b7280;line-height:1.6;margin-top:24px">Chris Garlick · <a href="{{ config('cg-cms.site.domain') }}" style="color:#6b7280">chrisgarlick.com</a></p>
</div>
