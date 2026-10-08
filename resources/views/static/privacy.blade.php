{{-- Ported verbatim from privacy.astro. Copy is unchanged, including its dashes: Phase 4 brings the live site over as it is. --}}
@extends('layouts.base')

@section('head')
    <x-cms-seo
        title="Privacy Policy"
        description="How Chris Garlick handles your data. UK GDPR compliant. Plain English privacy policy covering the contact form, the AI readiness audit, resource downloads, and how analytics work."
        :json-ld="[[
            '@type' => 'WebPage',
            'name' => 'Privacy Policy',
            'url' => 'https://chrisgarlick.com/privacy',
            'description' => 'How Chris Garlick handles your personal data under UK GDPR.',
            'inLanguage' => 'en-GB',
            'dateModified' => '2026-05-14',
        ]]"
    />
@endsection

@section('content')
    <section class="py-20 md:py-24">
      <div class="mx-auto max-w-[720px] px-5 md:px-8">
        <p class="mb-3 font-body text-xs font-medium uppercase tracking-widest text-text-secondary">Legal</p>
        <h1 class="mb-4 font-display text-[36px] text-text-primary md:text-[48px]">Privacy Policy</h1>
        <p class="mb-12 text-sm text-text-tertiary">Last updated: 14 May 2026 · Version 2026-05-14</p>

        <div class="prose-custom space-y-8 text-base leading-[1.75] text-text-secondary">

          <div>
            <h2 class="mb-4 font-display text-2xl text-text-primary">Who I am</h2>
            <p>This site is operated by Chris Garlick, an AI implementation partner based in the United Kingdom. I am the sole data controller for the personal data described here.</p>
            <p class="mt-2">Website: <a href="https://chrisgarlick.com" class="text-accent hover:text-accent-hover">chrisgarlick.com</a> · General contact: <a href="mailto:chris@chrisgarlick.com" class="text-accent hover:text-accent-hover">chris@chrisgarlick.com</a> · Privacy enquiries and data-subject requests: <a href="mailto:privacy@chrisgarlick.com" class="text-accent hover:text-accent-hover">privacy@chrisgarlick.com</a></p>
          </div>

          <div>
            <h2 class="mb-4 font-display text-2xl text-text-primary">What data I collect and why</h2>

            <h3 class="mb-2 mt-4 font-display text-lg text-text-primary">The contact form at /contact</h3>
            <p>When you submit the form at <a href="/contact" class="text-accent hover:text-accent-hover">/contact</a>, I collect your name, email address and message, and optionally your company, what the project is about and how you found this site.</p>
            <p class="mt-2">I use this data to respond to your enquiry and assess whether my services are a fit.</p>
            <p class="mt-2"><strong class="text-text-primary">Lawful basis:</strong> Legitimate interest (UK GDPR Article 6(1)(f)). You are contacting me about my services; I need your details to respond.</p>

            <h3 class="mb-2 mt-6 font-display text-lg text-text-primary">The AI readiness audit at /audit</h3>
            <p>When you submit the form at <a href="/audit" class="text-accent hover:text-accent-hover">/audit</a>, I collect your name, email, company name, website, sector, team size, your stated bottleneck, and any optional details you choose to share (budget range, six-month goal, sector-specific tooling). I also record your IP address and browser user-agent to prevent abuse of the form.</p>
            <p class="mt-2">I use this data to produce a personalised AI readiness audit, which is delivered to you as a PDF within 24 hours of submission.</p>
            <p class="mt-2"><strong class="text-text-primary">Lawful basis:</strong> Contract (UK GDPR Article 6(1)(b)). You are requesting a service from me and I need this data to deliver it.</p>
            <p class="mt-2">The only automated email you will ever receive from /audit is the audit PDF itself. Any further conversation about your audit is personal correspondence sent manually by me from my own inbox. There is no automated marketing sequence and no marketing list.</p>

            <h3 class="mb-2 mt-6 font-display text-lg text-text-primary">The site audit tool at /tools/site-audit</h3>
            <p>When you submit a URL for a free technical audit, I record the URL, your IP address, and the resulting score data so I can investigate abuse and improve the tool. I do not collect your email at this step.</p>
            <p class="mt-2"><strong class="text-text-primary">Lawful basis:</strong> Legitimate interest (UK GDPR Article 6(1)(f)).</p>

            <h3 class="mb-2 mt-6 font-display text-lg text-text-primary">Resource downloads at /resources</h3>
            <p>When you request a free resource at <a href="/resources" class="text-accent hover:text-accent-hover">/resources</a>, I collect your email and any optional name, company, and sector. The download itself is delivered to you transactionally. Marketing-style emails are only sent if you tick the explicit consent box.</p>
            <p class="mt-2"><strong class="text-text-primary">Lawful basis:</strong> Contract for the download itself; Consent (UK GDPR Article 6(1)(a)) for any subsequent marketing email, which you can withdraw at any time using the link in any such email.</p>
          </div>

          <div>
            <h2 class="mb-4 font-display text-2xl text-text-primary">Analytics</h2>
            <p>This site uses Google Analytics 4, loaded via Google Tag Manager, to understand how visitors find and use the site. GA4 sets cookies in your browser and records pseudonymous data including page views, referrer URLs, approximate location (city level), browser, and device type.</p>
            <p class="mt-2">I do not link analytics data to your form submissions or personally identify visitors. Google may process this data in the United States under its published Data Processing Addendum.</p>
          </div>

          <div>
            <h2 class="mb-4 font-display text-2xl text-text-primary">Who processes your data on my behalf (sub-processors)</h2>
            <p><strong class="text-text-primary">Anthropic</strong> (AI content generation) — when you submit the /audit form, your submission is sent to the Claude API to generate the audit content. Anthropic processes data in the United States under its published Data Processing Addendum and is configured for zero-retention mode: your data is not retained by Anthropic and is not used for model training.</p>
            <p class="mt-2"><strong class="text-text-primary">Resend</strong> (transactional email delivery) — processes your name and email address to send confirmation, audit, and resource-delivery emails. US-based, Standard Contractual Clauses in place.</p>
            <p class="mt-2"><strong class="text-text-primary">Google</strong> (Google Analytics 4 + Google Tag Manager) — processes pseudonymous analytics data as described above.</p>
            <p class="mt-2"><strong class="text-text-primary">DigitalOcean</strong> (hosting) — server logs may contain IP addresses in transit. UK and EU hosting regions used where applicable.</p>
            <p class="mt-2">I do not sell, rent, or share your personal data with anyone else.</p>
          </div>

          <div>
            <h2 class="mb-4 font-display text-2xl text-text-primary">How long I keep your data</h2>
            <p><strong class="text-text-primary">Contact form submissions:</strong> retained until the purpose is fulfilled, then deleted within 90 days unless there is a legal reason to retain them.</p>
            <p class="mt-2"><strong class="text-text-primary">Audit submissions (/audit):</strong> retained for 90 days if the audit was never sent (abandoned in workflow); 24 months if the audit was sent and there was no further engagement; up to 7 years if you went on to become a client (for HMRC and contract-record reasons). After 7 years, client records are anonymised.</p>
            <p class="mt-2"><strong class="text-text-primary">Resource-download leads:</strong> retained while marketing consent is active and for 24 months after consent is withdrawn or last engagement.</p>
            <p class="mt-2"><strong class="text-text-primary">Site audit logs (/tools/site-audit):</strong> retained for 12 months for abuse-prevention purposes, then deleted.</p>
          </div>

          <div>
            <h2 class="mb-4 font-display text-2xl text-text-primary">Your rights</h2>
            <p>Under UK GDPR you have the right to access, rectify, erase, restrict, object to processing of, and port your data, as well as the right to withdraw consent for any processing that relies on consent.</p>
            <p class="mt-2"><strong class="text-text-primary">The fastest way to remove your audit data</strong> is the deletion link in the bottom of the audit-delivery email I send you. One click, no login, no need to email me first.</p>
            <p class="mt-2">For any other rights request, or if you have lost the deletion link, email <a href="mailto:privacy@chrisgarlick.com" class="text-accent hover:text-accent-hover">privacy@chrisgarlick.com</a> from the email address you submitted with. I will action your request within 30 days.</p>
            <p class="mt-2">If you are not satisfied with my response, you can complain to the Information Commissioner's Office (ICO): <a href="https://ico.org.uk" class="text-accent hover:text-accent-hover" target="_blank" rel="noopener">ico.org.uk</a></p>
          </div>

          <div id="cookies">
            <h2 class="mb-4 font-display text-2xl text-text-primary">Cookies</h2>
            <p><strong class="text-text-primary">No cookies are set on this site until you explicitly accept them via the cookie banner.</strong> If you reject, no analytics cookies are ever set; if you accept, Google Analytics 4 sets the standard <code>_ga</code> and <code>_ga_*</code> first-party cookies to measure aggregate usage.</p>
            <p class="mt-2">Cookies on this site are never used for advertising, retargeting, or personalisation. You can change your preference at any time using the "Cookie preferences" link in the site footer. Rejecting consent after previously accepting will clear any analytics cookies that were already set.</p>
            <p class="mt-2">You can also block analytics cookies via your browser settings or by using an ad blocker. Doing so will not affect any functionality on the site.</p>
          </div>

          <div>
            <h2 class="mb-4 font-display text-2xl text-text-primary">Changes</h2>
            <p>I will update this page if anything changes. The "last updated" date and version number at the top reflect the most recent revision. For audit submissions, the privacy notice version that was live at the time of your submission is recorded with your data, so you can always check exactly which version of the policy you agreed to.</p>
          </div>

        </div>
      </div>
    </section>
@endsection
