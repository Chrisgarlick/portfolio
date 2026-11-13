---
title: "Taking over a WordPress site: what I check in the first hour"
slug: taking-over-a-wordpress-site
excerpt: "What I check in the first hour with an inherited WordPress site: backups, versions, plugins, logins, hosting and speed. Most you can run yourself."
services:
  - wordpress
seo_title: "Taking over a WordPress site: a first-hour checklist"
seo_description: "What I check in the first hour with an inherited WordPress site: backups, versions, plugins, logins, hosting and speed. Most you can run yourself."
status: draft
---

A lot of WordPress sites aren't new when they reach me. Someone built them a few years ago, then moved on. The site still works, mostly. But it's slow, an update broke the contact form, or nobody is quite sure who has the login.

Before I change anything, I spend an hour finding out what I've been handed. Not fixing. Just looking. That hour decides whether the site needs a rescue or a rebuild, and it stops me breaking something I didn't know was there.

Here's what I check, in the order I check it. You can run a lot of it yourself.

## 01 Can I undo anything I change?

Backups come first, because every other check is safer once I know I can undo it.

- Is there a backup outside the hosting account? A backup stored on the same server as the site goes down with the site.
- When was the last one, and does it include both the files and the database?
- Has anyone ever restored from it? A backup nobody has restored is a hope, not a backup.

If there's nothing I trust, I take my own copy before I touch anything: the database with `wp db export` and the `wp-content` folder. I'd rather spend five minutes on that than explain a lost page.

## 02 Versions: WordPress, PHP and every plugin

With WP-CLI on the server, three commands tell me most of the story:

- `wp core version` for WordPress itself
- `wp plugin list` for every plugin, whether it's active, and whether an update is waiting
- `wp theme list` for the theme and any parent theme

Then the PHP version, from **Tools → Site Health → Info → Server**. A site on a PHP version that no longer gets security fixes is one of the commonest problems on inherited sites, and often the reason an update was put off.

No WP-CLI? **Dashboard → Updates** and **Site Health** show the same things, more slowly.

## 03 Plugins nobody chose on purpose

The plugin list says a lot about a site's history. For each one, I ask:

- **Is it still maintained?** On WordPress.org, the "Last updated" date and "Tested up to" version tell you. Two years without an update is a warning.
- **Where did it come from?** A premium plugin with no licence, or one downloaded from a "free premium plugins" site, is a nulled copy. Nulled plugins are a common way malware gets in, and they never get security updates.
- **Does anything use it?** Deactivated plugins still sit on the server. Active ones that duplicate each other (two SEO plugins, three caching plugins) fight.
- **Is there a page builder?** Elementor, Divi or WPBakery shape everything else. They decide how hard the site is to speed up and how hard it is to move away from.

[Chris to add: a real example of a plugin list that told the story of a site]

## 04 Who can log in?

**Users → All Users**, filtered to Administrator. I'm looking for:

- Admin accounts for people who've left, old agencies, or names nobody recognises
- An account called `admin`, which is the first username every bot tries
- Whether two-factor login is on for the accounts that remain

The same question goes beyond WordPress: who has the hosting login, the domain registrar, the DNS, Google Analytics and Search Console? Finding out now beats finding out when the domain is about to expire.

## 05 wp-config.php and the server

`wp-config.php` holds the database password and a handful of switches. I check:

- `WP_DEBUG` is off on the live site, so errors aren't printed to visitors
- `DISALLOW_FILE_EDIT` is set to `true`, so nobody can edit theme and plugin code from the dashboard
- The security keys and salts aren't the defaults or blank
- `DISABLE_WP_CRON` and whether a real server cron job replaces it. If neither runs, scheduled posts and plugin tasks quietly stop.

Then file permissions (folders at 755, files at 644, `wp-config.php` tighter), and the security headers the server sends. A quick look at the response headers in the browser's developer tools shows whether `Strict-Transport-Security`, `X-Content-Type-Options` and a sensible `Content-Security-Policy` are there.

## 06 Hosting and caching

Shared hosting, a managed WordPress host and a VPS behave differently, so I check which it is before I blame WordPress for being slow.

Then the caching layers, because there are often several:

- A caching plugin (WP Rocket, W3 Total Cache, LiteSpeed Cache)
- Caching on the host itself
- A CDN in front, such as Cloudflare
- An object cache like Redis, if the host offers one

Two layers doing the same job cause the "I changed it but nothing happened" problems. I write down which layer does what before changing any of them.

## 07 Speed, measured

I run the home page and one or two busy inner pages through PageSpeed Insights. The field data at the top, from real Chrome users, matters more than the lab score underneath. I note Largest Contentful Paint, Interaction to Next Paint and Cumulative Layout Shift, so I can show the before and after later.

On a copy of the site, Query Monitor shows the slow database queries and which plugin made them.

## 08 Does anything quietly not work?

The last few checks are about things that fail without telling anyone:

- **Forms and email.** I send a test through every form. WordPress sends mail through the server by default, and a lot of it lands in spam or never arrives. An SMTP plugin connected to a proper mail service, with SPF, DKIM and DMARC records on the domain, fixes most of it.
- **Uptime.** Is anything watching the site? If not, the owner finds out it's down from a customer.
- **Staging.** Is there a copy to test updates on, or does every update happen on the live site?
- **Analytics and consent.** Is tracking installed once, and does the cookie banner block it until someone agrees?

## The first-hour checklist

- Backup outside the server, tested by a restore
- WordPress, PHP, theme and plugin versions
- Abandoned, nulled, duplicate and inactive plugins
- Page builder, and how much of the site depends on it
- Administrator accounts and two-factor login
- Who owns the hosting, domain, DNS and analytics logins
- `wp-config.php` constants, cron and file permissions
- Security headers
- Hosting type and every caching layer
- PageSpeed Insights field data for key pages
- A test message through every form
- Uptime monitoring, staging and cookie consent

A downloadable version of this checklist is coming soon.

## Rescue or rebuild?

After an hour I usually have a good sense of which way it goes. These are the signals I weigh:

| Leans towards rescue | Leans towards rebuild |
|---|---|
| A standard theme, or a custom one that's readable | A theme nobody can edit without breaking the layout |
| A handful of maintained plugins | Dozens of plugins, several abandoned or nulled |
| Updates are behind, but apply cleanly on staging | Updates break the site and have been put off for years |
| Slow because of images, caching or hosting | Slow because of the page builder and how pages are built |
| The content is right and the structure works | The site no longer matches what the business does |

Neither is a failure. A rescue keeps what works and fixes what doesn't. A rebuild is worth it when the foundations cost more to prop up than to replace. When I rebuild, I use a block theme and one small plugin of my own, built around the site's content rather than a page builder, so the next person to take it over has an easier first hour than I did.

[Chris to confirm: happy to mention the block theme and own plugin approach here]

## Next step

Don't like what some of these checks found? That's normal for an inherited site. Have a look at [how I work on WordPress sites](/services/wordpress), or [tell me about your site](/start) and what's going wrong. A few lines is enough.
