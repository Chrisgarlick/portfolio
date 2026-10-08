<?php

declare(strict_types=1);

/*
|------------------------------------------------------------------------------
| /for/ pages: the operating-model axis
|------------------------------------------------------------------------------
|
| Extracted from the Astro site (src/pages/for/*.astro), where each page was a
| ForPage component with these as props. Data rather than templates, as plan
| section 6 has it: one Blade view renders all five, and changing a page is an
| edit here rather than a deploy of new markup.
|
| `index` is the listing at /for, in display order.
|
*/

return [

    'index' => [
        [
            'slug' => 'agency-starters',
            'label' => 'Agency starters',
            'description' => 'Building an AI-enabled agency from scratch. You + a stack, not a team.',
            'available' => true,
        ],
        [
            'slug' => 'consultants',
            'label' => 'Consultants',
            'description' => 'Independent consultants productising their methodology. One workshop, ten content pieces.',
            'available' => true,
        ],
        [
            'slug' => 'freelancers',
            'label' => 'Freelancers',
            'description' => 'Take on more clients without adding hours. AI handles the overhead, not the craft.',
            'available' => true,
        ],
        [
            'slug' => 'solo-operators',
            'label' => 'Solo operators',
            'description' => 'One-person businesses running on AI. Client work, admin, marketing, all from one head.',
            'available' => true,
        ],
        [
            'slug' => 'tradespeople',
            'label' => 'Tradespeople',
            'description' => 'Your work speaks for itself. Let AI handle the posting, follow-ups and reviews.',
            'available' => true,
        ],
    ],

    'pages' => [
        'agency-starters' => [
            'audience' => 'Agency starters',
            'slug' => 'agency-starters',
            'headline' => 'You don\'t need a team. You need the right stack.',
            'subhead' => 'Most solo-founder agencies stall because operational overhead grows linearly with clients. A small-team-with-AI-stack reverses the curve. Work scales, overhead doesn\'t.',
            'not_doing' => [
                'You\'re either doing everything yourself, or burning cash on hires you can\'t yet afford.',
                'Onboarding takes a day per client because nothing is templated.',
                'Ad campaigns ship without proper copy variants because there\'s no copywriter on the team.',
                'Monthly reports get cobbled together at the eleventh hour because there\'s no account manager.',
                'Cold outreach either doesn\'t happen at all, or costs you a VA\'s salary to keep running.',
            ],
            'why_it_matters' => 'Most solo-founder agencies don\'t fail because the work isn\'t good. They fail in the first eighteen months because the operational overhead grows linearly with every new client. You hit five clients, the admin eats your evenings. You hit ten, you either hire (and watch the margin disappear) or cap there. A small-team-with-AI-stack flips that maths. Your delivery layer scales without adding headcount. Margin stays where it should: with the founder.',
            'workflows' => [
                [
                    'workflow' => 'Full client onboarding from one brief',
                    'saving' => 'Days to 30 mins',
                    'output' => 'Contracts, welcome docs, kickoff comms — all branded and sent',
                ],
                [
                    'workflow' => 'AI delivery stack replacing a 5-person team',
                    'saving' => '£10k/mo to £200-500/mo',
                    'output' => 'Ad copy, funnel pages, follow-up sequences, booking automation',
                ],
                [
                    'workflow' => 'Monthly reporting that looks senior',
                    'saving' => '4 hrs/month to 20 mins',
                    'output' => 'Client-branded reports with insights, not just numbers',
                ],
                [
                    'workflow' => 'Cold outreach personalised at scale',
                    'saving' => 'VA cost to zero',
                    'output' => 'Booked calls without a sales setter',
                ],
            ],
            'what_you_get' => [
                'A documented stack you can hand to your future self in 12 months and not be embarrassed by',
                'The first onboarding template fully filled out, ready to clone for client number two',
                'Three live automations saving time from week one',
                'A delivery model that scales past your first three clients without breaking',
            ],
            'resource_slug' => 'zero-team-agency-playbook',
            'resource_title' => 'The Zero-Team Agency Playbook',
            'resource_teaser' => 'The full stack, the prompts, the pricing maths, and the point at which you actually should hire. From first client to £10k/month without a team.',
            'related_industry' => [
                'slug' => 'ai-for-agencies',
                'label' => 'Established agencies',
            ],
            'siblings' => [
                [
                    'slug' => 'consultants',
                    'label' => 'Consultants',
                ],
                [
                    'slug' => 'freelancers',
                    'label' => 'Freelancers',
                ],
                [
                    'slug' => 'solo-operators',
                    'label' => 'Solo operators',
                ],
                [
                    'slug' => 'tradespeople',
                    'label' => 'Tradespeople',
                ],
            ],
            'seo_title' => 'AI Agency Stack for Solo Founders | Chris Garlick',
            'seo_description' => 'Build an AI-enabled agency that ships like a five-person team without hiring one. Stack, onboarding, delivery, reporting and outreach — UK perspective.',
            'seo_keywords' => 'ai agency uk, solo agency, no team agency, ai agency stack, productised agency uk, ai for marketing agency, solo founder agency',
        ],
        'consultants' => [
            'audience' => 'Consultants',
            'slug' => 'consultants',
            'headline' => 'Your frameworks are worth more than your one-to-one hours.',
            'subhead' => 'The most valuable thing you own is your methodology. AI turns it into content, courses and inbound while you focus on delivery.',
            'not_doing' => [
                'Trading hours for revenue with no leverage — every billable hour is the only billable hour.',
                'Your methodology lives in your head and a handful of slide decks no one outside your clients ever sees.',
                'The same insight gets delivered to 50 clients in 50 conversations, instead of once in market to 5,000 readers.',
                'Authority content never gets written because it\'s not billable and the calendar is full.',
                'LinkedIn presence is patchy — bursts around launches, silence in between.',
            ],
            'why_it_matters' => 'The asset isn\'t your time. It\'s your methodology. Time scales linearly with hours worked. Methodology scales infinitely once it\'s in market. The consultants who break out of the hours-for-money trap in 2026 are the ones treating their frameworks as the product, with one-to-one work as a delivery wrapper around them. The ones who don\'t keep raising rates until the rates become the bottleneck.',
            'workflows' => [
                [
                    'workflow' => 'One workshop into 10 content pieces',
                    'saving' => '1 day to 1 hr',
                    'output' => 'Blog post, 5 LinkedIn posts, email, 3 short-form video scripts',
                ],
                [
                    'workflow' => 'Weekly thought leadership from your frameworks',
                    'saving' => 'Never done to weekly',
                    'output' => 'Authority content at volume — your voice, not generic AI',
                ],
                [
                    'workflow' => 'Short-form video scripts from existing blog posts',
                    'saving' => 'Production cost to zero',
                    'output' => 'Reels and Shorts without a studio or editor',
                ],
                [
                    'workflow' => 'SEO landing pages for each niche you serve',
                    'saving' => 'Months to days',
                    'output' => 'Targeted organic traffic per vertical, not generic brand traffic',
                ],
            ],
            'what_you_get' => [
                'Six months of content extracted from one methodology document',
                'A repeatable extraction process you run for every new framework you develop',
                'A LinkedIn cadence that compounds instead of dying between launches',
                'The first three SEO landing pages live, ranking for your real keywords',
            ],
            'resource_slug' => 'one-framework-six-months-of-content',
            'resource_title' => 'One Framework, Six Months of Content',
            'resource_teaser' => 'The exact extraction process. Take one methodology and produce a half-year content calendar across blog, LinkedIn, email, video and landing pages. The prompts, the cadence, the sequencing.',
            'siblings' => [
                [
                    'slug' => 'agency-starters',
                    'label' => 'Agency starters',
                ],
                [
                    'slug' => 'freelancers',
                    'label' => 'Freelancers',
                ],
                [
                    'slug' => 'solo-operators',
                    'label' => 'Solo operators',
                ],
                [
                    'slug' => 'tradespeople',
                    'label' => 'Tradespeople',
                ],
            ],
            'seo_title' => 'AI Content System for Independent Consultants | Chris Garlick',
            'seo_description' => 'Turn your methodology into six months of content. The extraction system independent consultants use to build authority without writing every word themselves.',
            'seo_keywords' => 'ai for consultants, productise consulting, consulting content marketing, consulting frameworks, thought leadership ai, repurpose content consulting',
        ],
        'freelancers' => [
            'audience' => 'Freelancers',
            'slug' => 'freelancers',
            'headline' => 'Take on more clients without taking on more hours.',
            'subhead' => 'AI doesn\'t replace the craft. It removes the overhead that stops the craft from filling your calendar.',
            'not_doing' => [
                'Writing every proposal from scratch when the brief lands — three to four hours of unpaid work per opportunity.',
                'Capping client count at whatever number fits around the admin, not whatever number your delivery can actually handle.',
                'Letting LinkedIn go silent between projects, then trying to revive it cold when the pipeline thins.',
                'Skipping ad copy tests and paid lead gen because you\'re not a copywriter and an agency won\'t touch the budget.',
                'Onboarding every client by hand, which means it\'s inconsistent and quietly stressful.',
            ],
            'why_it_matters' => 'The bottleneck isn\'t your skill, it\'s everything surrounding it. A senior freelance designer, developer or copywriter can deliver three to five client projects a month. What stops most at two or three is the proposal writing on Friday nights, the LinkedIn that goes silent during delivery, the onboarding emails sent at midnight, the lead gen that never quite happens. AI handles the overhead. Your hourly rate stops being capped by the admin that doesn\'t pay for itself.',
            'workflows' => [
                [
                    'workflow' => 'Proposal generation from a brief',
                    'saving' => '2 hrs to 10 mins',
                    'output' => 'Polished, personalised proposal in your voice — not generic',
                ],
                [
                    'workflow' => 'LinkedIn posts from finished client work',
                    'saving' => 'Silent to weekly',
                    'output' => 'Consistent presence that drives inbound while you deliver',
                ],
                [
                    'workflow' => 'Ad copy variants for paid lead gen',
                    'saving' => 'Agency cost to zero',
                    'output' => 'Ten tested variants per campaign without hiring a copywriter',
                ],
                [
                    'workflow' => 'Automated client onboarding',
                    'saving' => 'Manual every time to set-and-forget',
                    'output' => 'Clients feel looked after from day one, consistently',
                ],
            ],
            'what_you_get' => [
                'A proposal pack you clone for every new brief — first one takes 30 mins, every one after that takes 10',
                'A brief-to-proposal prompt that produces 80% of the doc in your voice in one pass',
                'A LinkedIn cadence that runs even when you\'re heads-down on delivery',
                'Three onboarding emails that build trust before the project starts — same flow, every client',
            ],
            'resource_slug' => 'freelancers-ai-proposal-pack',
            'resource_title' => 'The Freelancer\'s AI Proposal Pack',
            'resource_teaser' => 'Win more clients, write less. Three proposal templates, the brief-to-proposal prompt, the follow-up sequence, and the onboarding kit I use with my own clients. Clone it, fill in the variables, ship.',
            'siblings' => [
                [
                    'slug' => 'agency-starters',
                    'label' => 'Agency starters',
                ],
                [
                    'slug' => 'consultants',
                    'label' => 'Consultants',
                ],
                [
                    'slug' => 'solo-operators',
                    'label' => 'Solo operators',
                ],
                [
                    'slug' => 'tradespeople',
                    'label' => 'Tradespeople',
                ],
            ],
            'seo_title' => 'AI Tools for Freelancers | Proposal & Onboarding System | Chris Garlick',
            'seo_description' => 'Win more clients without spending Friday nights on proposals. The AI proposal pack, LinkedIn extraction prompt and onboarding flow for UK freelancers.',
            'seo_keywords' => 'ai for freelancers, freelancer proposal template, brief to proposal ai, freelance ai workflow, freelance client onboarding, freelance scaling uk',
        ],
        'solo-operators' => [
            'audience' => 'Solo operators',
            'slug' => 'solo-operators',
            'headline' => 'Running a one-person business is hard enough. AI should be doing the heavy lifting.',
            'subhead' => 'Client work, admin, marketing, social, follow-ups, reviews — all on one head. AI handles the parts that never get done.',
            'not_doing' => [
                'The marketing that always gets pushed to weekends and then doesn\'t happen.',
                'Following up on quotes and leads, because every minute is either billable or admin-emergency.',
                'Asking clients for Google reviews — you mean to, you never quite get around to it.',
                'Writing case studies, because finished projects don\'t pay you to talk about them.',
                'Maintaining a blog or any kind of SEO presence — no time, no obvious return.',
            ],
            'why_it_matters' => 'The trap of a one-person business isn\'t lack of work. It\'s that the things that would compound your business never happen, because there\'s no margin in the day for them. The content cadence dies after week three. The review chase happens once and then never. Case studies pile up unwritten. Six months in, you\'re as busy as ever, but no further forward. AI handles the compounding work so it actually compounds, while you stay on the work that pays.',
            'workflows' => [
                [
                    'workflow' => 'Weekly content calendar from one voice note',
                    'saving' => '3 hrs to 10 mins',
                    'output' => '7 days of social posts, drafted in your voice and scheduled',
                ],
                [
                    'workflow' => 'Automated review requests after every job',
                    'saving' => 'Manual chase to set-and-forget',
                    'output' => 'More Google reviews, less awkward asking, better local SEO',
                ],
                [
                    'workflow' => 'Case studies from a 5-minute client debrief',
                    'saving' => '2 hrs to 15 mins',
                    'output' => 'Published case study, SEO-ready, on the website that night',
                ],
                [
                    'workflow' => 'Monthly SEO blog post from a topic you know',
                    'saving' => 'Never done to done',
                    'output' => 'Organic traffic compounding, no writer needed',
                ],
            ],
            'what_you_get' => [
                'A voice-note-to-week-of-content pipeline that runs in 10 minutes a Monday',
                'A post-job automation that asks for reviews so you never have to',
                'Your first case study live, plus the repeatable extraction prompt for every project after',
                'The first SEO blog post drafted from your own expertise, not generic content',
            ],
            'resource_slug' => 'ai-stack-under-two-hours-a-day',
            'resource_title' => 'The Solo Operator AI Stack',
            'resource_teaser' => 'The exact stack for running a one-person business in under two hours of admin a day. Tools, prompts, weekly cadence, and the order to roll them out.',
            'related_industry' => [
                'slug' => 'ai-for-law-firms',
                'label' => 'Sole-practice law firms',
            ],
            'siblings' => [
                [
                    'slug' => 'agency-starters',
                    'label' => 'Agency starters',
                ],
                [
                    'slug' => 'consultants',
                    'label' => 'Consultants',
                ],
                [
                    'slug' => 'freelancers',
                    'label' => 'Freelancers',
                ],
                [
                    'slug' => 'tradespeople',
                    'label' => 'Tradespeople',
                ],
            ],
            'seo_title' => 'The Solo Operator AI Stack | Run a One-Person Business in 2 Hours a Day | Chris Garlick',
            'seo_description' => 'The exact AI stack for running a one-person business in under two hours of admin a day. Voice-note content, automated reviews, case studies, monthly SEO posts.',
            'seo_keywords' => 'ai for solo operators, one person business ai, sole trader ai uk, solopreneur ai stack, small business automation uk, voice note to content, automated review requests',
        ],
        'tradespeople' => [
            'audience' => 'Tradespeople',
            'slug' => 'tradespeople',
            'headline' => 'Your work speaks for itself. Let AI make sure the right people see it.',
            'subhead' => 'You don\'t have time to post, follow up, or run ads. AI does all of that from your phone, in minutes, without sounding like a robot.',
            'not_doing' => [
                'Doing brilliant work that nobody outside the customer\'s WhatsApp ever sees.',
                'Asking for Google reviews when you remember, never when you don\'t.',
                'Posting on Instagram when you can be bothered, then six weeks of nothing.',
                'Quotes sent and never followed up — chasing feels awkward, so it doesn\'t happen.',
                'Seasonal jobs (boiler service in October, gutter clean in November) never get advertised in time.',
            ],
            'why_it_matters' => 'The trade is the easy part. You\'re good at the trade. The hard part is being the marketing department, the office manager, the sales follow-up person, and the social media manager when you\'re already on a roof or under a sink. AI doesn\'t do the actual work. It does the photos, the posts, the reviews and the follow-ups that fill next month\'s calendar — so when you finish a job, the next one is already booked.',
            'workflows' => [
                [
                    'workflow' => 'Before/after Reels from phone photos',
                    'saving' => 'Agency cost to free',
                    'output' => 'Scroll-stopping videos that show your actual work, posted same day',
                ],
                [
                    'workflow' => 'Google Business posts on autopilot',
                    'saving' => 'Never done to weekly',
                    'output' => 'Better local SEO, more calls from people searching nearby',
                ],
                [
                    'workflow' => 'Seasonal campaign copy',
                    'saving' => '£500/campaign to £0',
                    'output' => 'Ready-to-fire ads for boiler service season, gutter clean, summer prep',
                ],
                [
                    'workflow' => 'Quote follow-up sequences',
                    'saving' => 'Manual chasing to automated',
                    'output' => 'Quotes get followed up three times without you doing anything awkward',
                ],
            ],
            'what_you_get' => [
                'A 5-minute Reel format you can run after every job — phone in your van, done before you drive home',
                'A weekly Google Business post that goes out automatically',
                'Three pre-built seasonal campaigns ready to fire when the season hits',
                'A quote follow-up sequence that runs in the background — more wins, no chasing',
            ],
            'resource_slug' => '5-ai-tools-tradespeople-2026',
            'resource_title' => '5 AI Tools Every Tradesperson Should Use in 2026',
            'resource_teaser' => 'Five tools, what each one\'s for, what they cost, the order to set them up. No marketing agency. No copywriter. All from your phone.',
            'siblings' => [
                [
                    'slug' => 'agency-starters',
                    'label' => 'Agency starters',
                ],
                [
                    'slug' => 'consultants',
                    'label' => 'Consultants',
                ],
                [
                    'slug' => 'freelancers',
                    'label' => 'Freelancers',
                ],
                [
                    'slug' => 'solo-operators',
                    'label' => 'Solo operators',
                ],
            ],
            'seo_title' => '5 AI Tools Every UK Tradesperson Should Use in 2026 | Chris Garlick',
            'seo_description' => 'The five AI tools that handle photos, posts, Google reviews, follow-ups and seasonal ads for UK tradespeople. Run it all from your phone in under 30 minutes a week.',
            'seo_keywords' => 'ai for tradespeople, ai for trades uk, plumber marketing ai, electrician marketing ai, builder marketing ai, checkatrade marketing, trades google reviews',
        ],
    ],

];
