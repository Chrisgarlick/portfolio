{{-- Ported verbatim from terms.astro. Copy is unchanged, including its dashes: Phase 4 brings the live site over as it is. --}}
@extends('layouts.base')

@section('head')
    <x-cms-seo
        title="Terms of Service"
        description="Terms of service for chrisgarlick.com — use of the site, intellectual property, application forms, limitation of liability. Governed by the laws of England and Wales."
        :json-ld="[[
            '@type' => 'WebPage',
            'name' => 'Terms of Service',
            'url' => 'https://chrisgarlick.com/terms',
            'description' => 'Terms of service for chrisgarlick.com, governed by the laws of England and Wales.',
            'inLanguage' => 'en-GB',
            'dateModified' => '2026-05-02',
        ]]"
    />
@endsection

@section('content')
    <section class="py-20 md:py-24">
      <div class="mx-auto max-w-[720px] px-5 md:px-8">
        <p class="mb-3 font-body text-xs font-medium uppercase tracking-widest text-text-secondary">Legal</p>
        <h1 class="mb-4 font-display text-[36px] text-text-primary md:text-[48px]">Terms of Service</h1>
        <p class="mb-12 text-sm text-text-tertiary">Last updated: 2 May 2026</p>

        <div class="prose-custom space-y-8 text-base leading-[1.75] text-text-secondary">

          <div>
            <h2 class="mb-4 font-display text-2xl text-text-primary">About this site</h2>
            <p>This website is operated by Chris Garlick. It provides information about AI workflow services and allows prospective clients to submit an application form. It is not a platform, marketplace, or SaaS product.</p>
          </div>

          <div>
            <h2 class="mb-4 font-display text-2xl text-text-primary">Use of the site</h2>
            <p>You may use this site to browse content and submit an application. You agree not to use the site for any unlawful purpose, attempt to gain unauthorised access, submit false information, or scrape content by automated means without written permission.</p>
          </div>

          <div>
            <h2 class="mb-4 font-display text-2xl text-text-primary">Intellectual property</h2>
            <p>All content on this site — including text, design, code, and case studies — is the intellectual property of Chris Garlick unless otherwise stated.</p>
          </div>

          <div>
            <h2 class="mb-4 font-display text-2xl text-text-primary">Application form</h2>
            <p>Submitting an application does not create a contract, obligation, or guarantee of service. It is an expression of interest. Any engagement will be subject to a separate agreement.</p>
          </div>

          <div>
            <h2 class="mb-4 font-display text-2xl text-text-primary">Limitation of liability</h2>
            <p>This site is provided "as is." To the maximum extent permitted by law, I am not liable for any indirect, incidental, or consequential damages arising from your use of this site. Nothing in these terms excludes liability for death or personal injury caused by negligence, fraud, or any other liability that cannot be excluded under English law.</p>
          </div>

          <div>
            <h2 class="mb-4 font-display text-2xl text-text-primary">Governing law</h2>
            <p>These terms are governed by the laws of England and Wales. Any disputes will be subject to the exclusive jurisdiction of the courts of England and Wales.</p>
          </div>

          <div>
            <h2 class="mb-4 font-display text-2xl text-text-primary">Contact</h2>
            <p>Questions about these terms: <a href="mailto:chris@chrisgarlick.com" class="text-accent hover:text-accent-hover">chris@chrisgarlick.com</a></p>
          </div>

        </div>
      </div>
    </section>
@endsection
