<?php

declare(strict_types=1);

/*
|------------------------------------------------------------------------------
| Form definitions
|------------------------------------------------------------------------------
|
| Plain arrays, so this could live in config if it ever needs to. Kept
| alongside schema.php for consistency instead.
|
| Lead capture is a form on each service page rather than gated downloads. That
| removes the resource funnel, the sector and stage segmentation, the download
| tracking and the Typeset render pipeline: a lot of machinery for a lead that
| now arrives directly from the page describing the work.
|
| Keep these short. Every field is a reason not to send the message.
|
| Never read environment variables here: this file is outside config/, and
| once production caches its config they come back null. Use config().
|
*/

return [

    'enquiry' => [
        'name' => 'Service enquiry',
        'notify' => config('cg-cms.site.contact_email'),
        'success' => 'Thanks. I read every message and will reply within a day or two.',
        'fields' => [
            'name' => [
                'label' => 'Your name',
                'type' => 'text',
                'rules' => ['required', 'string', 'max:120'],
            ],
            'email' => [
                'label' => 'Email',
                'type' => 'email',
                // Format only, deliberately not 'dns'. DNS validation makes a live
                // lookup inside the request: a slow resolver blocks one of four PHP
                // workers, an outage rejects every enquiry, and domains with unusual
                // MX records get turned away. Deliverability is the mail send's job.
                'rules' => ['required', 'email:rfc', 'max:255'],
            ],
            'company' => [
                'label' => 'Company',
                'type' => 'text',
                'rules' => ['nullable', 'string', 'max:120'],
            ],
            'message' => [
                'label' => 'What are you trying to do?',
                'type' => 'textarea',
                'rules' => ['required', 'string', 'min:20', 'max:4000'],
                'help' => 'The process, the bottleneck, roughly where you are up to.',
            ],
        ],
    ],

    /*
     * The /contact form, trimmed with the redesign (site_consolidation_plan.md
     * section 6) and again for the kit page: a name, an address and a
     * message. Project enquiries go to the /start brief. It posts to /contact
     * itself (`page`), so without JavaScript its errors and its thanks show
     * on the page.
     */
    'contact' => [
        'name' => 'Contact',
        'notify' => config('cg-cms.site.contact_email'),
        'success' => "Thanks, it's arrived. I read every message myself and reply within 48 hours.",
        'page' => 'contact',
        'source' => true,
        'fields' => [
            'name' => [
                'label' => 'Your name',
                'type' => 'text',
                'rules' => ['required', 'string', 'max:120'],
            ],
            'email' => [
                'label' => 'Email',
                'type' => 'email',
                // Format only, deliberately not 'dns'. See 'enquiry' above.
                'rules' => ['required', 'email:rfc', 'max:255'],
            ],
            'message' => [
                'label' => 'Your message',
                'type' => 'textarea',
                'placeholder' => 'A few lines is plenty.',
                'rules' => ['required', 'string', 'min:10', 'max:4000'],
            ],
        ],
        'messages' => [
            'name.required' => 'Tell me your name.',
            'email.required' => 'Give me an email address so I can reply.',
            'email.email' => 'That email address does not look right. Check it for typos.',
            'message.required' => 'Write a line or two so I know what it is about.',
            'message.min' => 'A little more, please: a sentence or two is plenty.',
            'message.max' => 'That is a lot to read. Keep it under 4,000 characters.',
        ],
    ],

    /*
     * The /start brief (Design nf_start, the three steps as one page's three
     * fieldsets). It posts to /start itself, so a browser without JavaScript
     * sees its errors on the same address (`page` is the kit page drawn with
     * them) and lands on /start/thanks (`redirect`) when it is sent. `source`
     * takes the landing page, the referrer and any UTM tags, filled in by
     * resources/js/site/form-source.js; without a script it is simply absent.
     * Budget is a band, never an amount, and both it and the timing are
     * optional: "Not sure" is always a fine answer.
     */
    'start' => [
        'name' => 'Project brief',
        'notify' => config('cg-cms.site.contact_email'),
        'success' => 'Thanks, I have your brief. I reply to every one within 48 hours.',
        'redirect' => '/start/thanks',
        'page' => 'start',
        'source' => true,
        'fields' => [
            'need' => [
                'label' => 'Type of work',
                'type' => 'choice',
                'options' => ['A Laravel app or tool', 'A WordPress site', 'AI in my business', 'Fix what an audit found', 'Not sure yet'],
                'rules' => ['required', 'in:A Laravel app or tool,A WordPress site,AI in my business,Fix what an audit found,Not sure yet'],
            ],
            'details' => [
                'label' => 'In a few lines',
                'type' => 'textarea',
                'placeholder' => "What you have now, and what you'd like it to do…",
                'rules' => ['required', 'string', 'min:10', 'max:4000'],
            ],
            'budget' => [
                'label' => 'Budget',
                'type' => 'choice',
                'options' => ['Small', 'Medium', 'Large', 'Not sure yet'],
                'rules' => ['nullable', 'in:Small,Medium,Large,Not sure yet'],
            ],
            'timeline' => [
                'label' => 'When would you like it live?',
                'type' => 'choice',
                'options' => ['As soon as possible', 'In the next month or two', 'In three months or more', 'No fixed date'],
                'rules' => ['nullable', 'in:As soon as possible,In the next month or two,In three months or more,No fixed date'],
            ],
            'name' => [
                'label' => 'Your name',
                'type' => 'text',
                'rules' => ['required', 'string', 'max:120'],
            ],
            'email' => [
                'label' => 'Email',
                'type' => 'email',
                // Format only, deliberately not 'dns'. See 'enquiry' above.
                'rules' => ['required', 'email:rfc', 'max:255'],
            ],
            'company' => [
                'label' => 'Company',
                'type' => 'text',
                'rules' => ['nullable', 'string', 'max:160'],
            ],
            'website' => [
                'label' => 'Website',
                'type' => 'text',
                'rules' => ['nullable', 'string', 'max:255'],
            ],
        ],
        // Said in words, next to the field, so an error never relies on colour.
        'messages' => [
            'need.required' => 'Pick the closest kind of work. Not sure yet is fine.',
            'need.in' => 'Pick one of the kinds of work listed.',
            'details.required' => 'Tell me in a few lines what you have and what you need.',
            'details.min' => 'A little more, please: a sentence or two is plenty.',
            'details.max' => "That is a lot to read. Keep it under 4,000 characters and I'll go through the rest with you on the call.",
            'budget.in' => 'Pick one of the budget bands, or leave it.',
            'timeline.in' => 'Pick one of the timings, or leave it.',
            'name.required' => 'Tell me your name.',
            'email.required' => 'Give me an email address so I can reply.',
            'email.email' => 'That email address does not look right. Check it for typos.',
        ],
    ],

];
