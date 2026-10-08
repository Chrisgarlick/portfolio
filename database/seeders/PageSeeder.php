<?php

declare(strict_types=1);

namespace Database\Seeders;

use Cg\Cms\Models\Entry;
use Illuminate\Database\Seeder;

/**
 * A block-built page exercising the interesting cases:
 *
 *  - rich text nested inside a block (Kritano issue 3d)
 *  - a block that queries another collection, so the page must end up
 *    depending on collection:article
 *  - the flat and nested block storage shapes, both of which the legacy data
 *    contains
 */
final class PageSeeder extends Seeder
{
    public function run(): void
    {
        Entry::query()->updateOrCreate(
            ['collection' => 'page', 'slug' => 'home', 'locale' => 'en'],
            [
                'title' => 'AI implementation for UK professional services',
                'status' => 'published',
                'seo' => [
                    'description' => 'I build AI workflows for law firms, accountancy practices and agencies.',
                ],
                'data' => ['content' => [
                    [
                        'type' => 'hero',
                        'data' => [
                            'label' => 'AI implementation',
                            'heading' => 'Move one manual process to an AI-assisted one',
                            'subtext' => 'Engagements start at £500. A few focused hours to remove a single bottleneck.',
                            'cta_label' => 'Book a diagnostic',
                            'cta_url' => '/diagnostic',
                            'cta_secondary_label' => 'See the work',
                            'cta_secondary_url' => '/work',
                            'theme' => 'dark',
                        ],
                    ],
                    [
                        // Nested rich text. The old stack could not pre-render
                        // this, so the Astro theme carried a second renderer.
                        'type' => 'text-section',
                        'data' => [
                            'heading' => 'What this actually involves',
                            'theme' => 'light',
                            'body' => [
                                'type' => 'doc',
                                'content' => [
                                    [
                                        'type' => 'paragraph',
                                        'content' => [
                                            ['type' => 'text', 'text' => 'Most firms describe the problem as a technology gap. It is almost always a '],
                                            ['type' => 'text', 'marks' => [['type' => 'bold']], 'text' => 'process gap'],
                                            ['type' => 'text', 'text' => '. The bottleneck is rarely the model.'],
                                        ],
                                    ],
                                    [
                                        'type' => 'bulletList',
                                        'content' => [
                                            ['type' => 'listItem', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Find the handoff that costs hours.']]]]],
                                            ['type' => 'listItem', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Automate that one step end to end.']]]]],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    [
                        // Flat storage shape, no `data` wrapper. The legacy CMS
                        // wrote both, so BlockRenderer normalises them.
                        'type' => 'columns',
                        'heading' => 'Where I work',
                        'column1_heading' => 'Law firms',
                        'column1_body' => 'Intake, document review, matter summaries.',
                        'column1_url' => '/industries/ai-for-law-firms',
                        'column1_cta_label' => 'For law firms',
                        'column1_tint' => 'workflow',
                        'column2_heading' => 'Accountancy',
                        'column2_body' => 'Onboarding, data entry, reconciliation.',
                        'column2_url' => '/industries/ai-for-accountancy-firms',
                        'column2_cta_label' => 'For accountants',
                        'column2_tint' => 'data',
                        'theme' => 'light',
                    ],
                    [
                        'type' => 'offer-cards',
                        'data' => [
                            'card1_label' => 'Start here',
                            'card1_name' => 'Diagnostic',
                            'card1_price' => 'From £500',
                            'card1_duration' => '1 week',
                            'card1_features' => "One bottleneck mapped\nA costed build plan\nNo obligation to proceed",
                            'card2_label' => 'Build',
                            'card2_name' => 'Implementation',
                            'card2_price' => 'From £2,500',
                            'card2_duration' => '2 to 6 weeks',
                            'card2_features' => "Built, tested, handed over\nYour stack, not mine\nRetainer optional",
                            'theme' => 'light',
                        ],
                    ],
                    [
                        // Queries the article collection. The page must end up
                        // depending on collection:article because of this.
                        'type' => 'blog-preview',
                        'data' => ['heading' => 'Recent writing', 'limit' => 3, 'theme' => 'light'],
                    ],
                    [
                        'type' => 'cta',
                        'data' => [
                            'heading' => 'Start with the bottleneck that costs you most',
                            'body' => 'Tell me the process. I will tell you whether it is worth automating.',
                            'cta_label' => 'Get in touch',
                            'cta_url' => '/contact',
                            'theme' => 'dark',
                        ],
                    ],
                ]],
            ],
        );
    }
}
