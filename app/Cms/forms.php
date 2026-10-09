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
     * section 6). The project type is optional and sorts leads on arrival.
     * The live form also asked for industry, team size and the "biggest
     * operational bottleneck", which belonged to the AI-only positioning.
     */
    'contact' => [
        'name' => 'Contact',
        'notify' => config('cg-cms.site.contact_email'),
        'success' => 'Thanks. I read every message myself and will reply as soon as I can.',
        'fields' => [
            'name' => [
                'label' => 'Name',
                'type' => 'text',
                'placeholder' => 'Your name',
                'rules' => ['required', 'string', 'max:120'],
            ],
            'email' => [
                'label' => 'Email',
                'type' => 'email',
                'placeholder' => 'you@company.com',
                // Format only, deliberately not 'dns'. See 'enquiry' above.
                'rules' => ['required', 'email:rfc', 'max:255'],
            ],
            'company' => [
                'label' => 'Company',
                'type' => 'text',
                'placeholder' => 'Optional',
                'rules' => ['nullable', 'string', 'max:160'],
            ],
            'project_type' => [
                'label' => 'What is it about?',
                'type' => 'select',
                'options' => ['Laravel', 'WordPress', 'AI', 'Not sure yet'],
                'rules' => ['nullable', 'in:Laravel,WordPress,AI,Not sure yet'],
            ],
            'message' => [
                'label' => 'What are you trying to build or fix?',
                'type' => 'textarea',
                'placeholder' => 'A few lines is plenty: what you have, what you need, and any timing.',
                'rules' => ['required', 'string', 'min:10', 'max:4000'],
            ],
            'referral' => [
                'label' => 'How did you find me?',
                'type' => 'text',
                'placeholder' => 'Optional',
                'rules' => ['nullable', 'string', 'max:255'],
            ],
        ],
    ],

];
