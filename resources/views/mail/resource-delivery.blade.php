{{-- Ported from the delivery email in server.ts. --}}
<div style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;max-width:560px;margin:0 auto;color:#111827">
    <p style="font-size:15px;line-height:1.6">Thanks{{ filled($lead->first_name) ? ', '.$lead->first_name : '' }}, here's your download.</p>
    <p style="margin:24px 0">
        <a href="{{ $url }}" style="display:inline-block;background:#1a1715;color:#ffffff;padding:12px 20px;border-radius:3px;text-decoration:none;font-size:14px;font-weight:500">Open {{ $resourceTitle }}</a>
    </p>
    <p style="font-size:13px;color:#6b7280;line-height:1.6">This link works for 7 days. Pick your format on the download page: Markdown, PDF or DOCX.</p>
    <p style="font-size:13px;color:#6b7280;line-height:1.6;margin-top:24px">Chris Garlick · <a href="{{ config('cg-cms.site.domain') }}" style="color:#6b7280">chrisgarlick.com</a></p>
</div>
