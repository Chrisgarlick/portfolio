<?php

declare(strict_types=1);

namespace Database\Seeders;

use Cg\Cms\Models\Entry;
use Illuminate\Database\Seeder;

/**
 * The three capabilities, replacing the sector-based /industries/ pages.
 *
 * AI implementation is framed as "this is what I can do" rather than a
 * targeted funnel, which is what the zero inbound from the sector pages
 * suggested it should have been all along.
 */
final class ServiceSeeder extends Seeder
{
    public function run(): void
    {
        $services = [
            'ai-implementation' => [
                'title' => 'AI implementation',
                'sort' => 1,
                'summary' => 'Take one manual process and make it an AI-assisted one. Built into your stack, tested, handed over.',
                'typical_timeline' => '2 to 6 weeks',
                'includes' => "One process mapped end to end\nA working build in your stack, not a demo\nThe prompts and logic documented so you can change them\nHandover, with the option of a retainer",
                'colour' => 'violet',
                'projects' => [],
                'body' => 'Most firms describe the problem as a technology gap. It is almost always a process gap. The bottleneck is rarely the model, it is the handoff either side of it, and that is where the hours go.',
            ],
            'website-building' => [
                'title' => 'Website building',
                'sort' => 2,
                'summary' => 'Fast, accessible sites that you can actually edit. No page builder bloat and no monthly platform fee.',
                'typical_timeline' => '2 to 4 weeks',
                'includes' => "Design and build\nA CMS you own rather than rent\nCore Web Vitals in the green on real hardware\nSEO fundamentals done properly, not bolted on",
                'colour' => 'blue',
                'projects' => ['cg-cms'],
                'body' => 'A site should load in under a second, be editable without a developer, and not cost a subscription to keep online. That is achievable and surprisingly rare.',
            ],
            'software-development' => [
                'title' => 'Software development',
                'sort' => 3,
                'summary' => 'Laravel and PHP applications: internal tools, integrations, and the unglamorous systems a business actually runs on.',
                'typical_timeline' => '4 weeks and up',
                'includes' => "Scoping, so the estimate means something\nBuilt with tests, because you will want to change it\nDeployed and documented\nOngoing support if you want it",
                'colour' => 'red',
                'projects' => ['typeset'],
                'body' => 'The interesting work is rarely the feature list. It is understanding the process well enough that the software makes it simpler rather than adding a place to click.',
            ],
        ];

        foreach ($services as $slug => $service) {
            Entry::query()->updateOrCreate(
                ['collection' => 'service', 'slug' => $slug, 'locale' => 'en'],
                [
                    'title' => $service['title'],
                    'status' => 'published',
                    'sort_order' => $service['sort'],
                    'data' => [
                        'summary' => $service['summary'],
                        'typical_timeline' => $service['typical_timeline'],
                        'includes' => $service['includes'],
                        'colour' => $service['colour'],
                        'projects' => $service['projects'],
                        'body' => [
                            'type' => 'doc',
                            'content' => [[
                                'type' => 'paragraph',
                                'content' => [['type' => 'text', 'text' => $service['body']]],
                            ]],
                        ],
                    ],
                ],
            );
        }
    }
}
