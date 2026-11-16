# Article edits: calls to action and self-links

Approved by Chris and applied on live, 16 Nov. 15 articles changed; each body's previous version is in storage/app/backups/article-cta-edits/<slug>.json on the server. Links were compared after following exact redirects, so an old address (/services/ai-engineering) counted as the page it lands on (/services/ai); that also caught one repeat in ai-vs-hiring-accountancy.

From the Kritano audit of 15 Nov ("Too many CTAs", 15 articles) and a scan of
the live article bodies on 16 Nov. The article template now ends every article
with one call to action (the author block's "Start a project"), so the links
written into the old bodies are extra.

Nothing here changes what an article says. Three kinds of edit:

**A. Remove the old closing block.** Six articles end with:

> **Want to find out which tasks on your site are costing you the most time?**
> Run a free audit → · Book a 30-min call →

It repeats the author block's button straight after it. Remove the block and
the rule above it.

**B. Link each page once.** Where the same page is linked several times (usually
/services/ai under the old service names "AI engineering", "custom AI agents",
"workflow automation", "data extraction"), keep the first link and leave the
later ones as plain text. The words stay.

**C. Unlink self-links.** Links that point at the article you're reading, mostly
old /for/ pages that now redirect to it. The words stay.

| Article | Links now | A | After A+B | C (text unlinked) |
|---|---|---|---|---|
| ai-data-extraction-uk-guide | 18 | | 8 | |
| ai-implementation-cost-uk | 13 | | 3 | |
| what-ai-implementation-means-law-firm | 10 | yes | 3 | |
| the-ai-implementation-playbook-for-service-businesses | 9 | yes | 2 | |
| why-79-of-enterprises-are-failing-at-ai-adoption | 5 | yes | 2 | |
| ai-adoption-disappointment-why-companies-fail | 5 | yes | 2 | |
| 51-of-code-on-github-is-ai-generated-that-should-worry-you | 5 | yes | 2 | |
| replacing-manual-data-entry-with-ai-agents | 4 | yes | 2 | |
| automate-client-intake-without-custom-software | 4 | yes | 2 | |
| agency-workflows-automate-first | 4 | yes | 2 | "agency", "AI for Agencies" |
| 5-ai-tools-tradespeople-2026 | 3 | | 2 | "UK tradespeople" |
| solo-operator-ai-stack | 1 | | 1 | "The Solo Operator AI Stack resource", "how I work with solo operators" |
| ai-client-onboarding-accountancy-uk | 1 | | 1 | "how I work with accountancy firms" |
| ai-proposal-pack-freelancers | 0 | | 0 | "full proposal pack resource", "wider freelancer workflow on the /for/freelancers page", "grab the freelancer's AI proposal pack here" |

"Links now" counts links to /contact, /start, /tools/site-audit and /services
in the body. ai-data-extraction-uk-guide keeps 8 because it links eight
different service pages; those old service names may be worth rewording when
the article is next reviewed.

**Not covered here (writing, phase 4):** the old service names in the text
("AI engineering", "custom AI agents"), the hyphens used as dashes in some
articles, and the "Where to next" lists that point at retired pages.

## How it's applied

On live, by editing each article's stored body (TipTap JSON) the way the
accountancy fix was: restore point first (cg-cms keeps a revision on every
save), then the edit, then a check that the rendered article still has every
paragraph and heading it had before. Not through `cms:entry:update`, which
takes markdown and would rewrite the formatting.
