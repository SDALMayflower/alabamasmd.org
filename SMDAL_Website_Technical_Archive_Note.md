# How the SMDAL Website Is Built and Run — A Technical Archive Note

*Written by Barrett Holt, for the Society's records. Last updated August 2, 2026.*

I'm writing this down so there's a real record somewhere of how alabamasmd.org actually works: what runs it, what each piece does, and how a change I make on my end turns into something a visitor sees. If I ever hand this off, or if I come back to it after a long gap, this should be enough to get oriented again without having to reverse-engineer my own work.

The short version: this is a plain static website (no database, no server-side app in the traditional sense) built with HTML, CSS, and JavaScript, stored in a GitHub repository, hosted for free through GitHub Pages, and sitting behind Cloudflare, which handles the domain's DNS, security, and a small piece of custom server-side logic I wrote to password-protect the members-only section. The domain itself, alabamasmd.org, is registered through GoDaddy. Everything below explains how those pieces fit together.

## 1. The domain: GoDaddy

I bought and renew alabamasmd.org through GoDaddy. GoDaddy's only job at this point is domain registration, meaning it holds the legal registration for the name and lets me control where its DNS records point. I don't host anything on GoDaddy itself. The domain's nameservers are set to point to Cloudflare, so Cloudflare is what actually answers when a browser asks "where do I find alabamasmd.org?"

If I ever need to renew the domain, transfer it, or change ownership details, that happens in the GoDaddy account. Nothing about the website's content or code lives there.

## 2. DNS, security, and the password gate: Cloudflare

Cloudflare sits in front of the whole site. It does three separate jobs for me:

**DNS.** Cloudflare is the authoritative DNS provider for alabamasmd.org. When someone types the domain into a browser, Cloudflare's DNS is what resolves it and routes the request onward to where the actual site lives (GitHub Pages, covered below).

**Security and caching layer (the "orange cloud").** Because the domain is proxied through Cloudflare rather than pointed directly at GitHub, every request passes through Cloudflare first. This gives me free SSL/TLS, DDoS protection, and a place to intercept specific requests before they ever reach GitHub. Right now I don't have any custom Cache Rules or Page Rules configured, so Cloudflare mostly just passes requests through.

**The Members Only password gate — a Cloudflare Worker.** This is the one piece of real backend logic on the whole site. I wrote a small JavaScript program called a Cloudflare Worker (its internal name is `proud-wind-3d4f`) that runs on Cloudflare's own servers, not on GitHub, and not in the visitor's browser. It's configured to intercept any request to `/members-only.html`, anything under `/members-only/`, and anything under `/member-docs/` (that's where the real membership directory PDF lives).

Here's what that Worker actually does: when someone requests one of those protected paths, the Worker checks for a session cookie. If there's no valid cookie, it shows a simple password page instead of the real content. When someone enters the correct password, the Worker sets a cookie whose value is a SHA-256 hash of that password, then redirects them to the page they originally wanted. On every future request, it just checks whether the cookie matches the current password's hash. That means if I ever change the shared password, every existing session gets invalidated automatically, since the hash won't match anymore. The Worker also handles logging out: visiting `/members-only/mo-signout` clears that cookie and sends the visitor back to the members landing page.

I want to flag one specific detail here because it caused real trouble and is worth remembering: I originally tried to trigger logout with a query string like `?logout=1` appended to the members-only URL. On the live custom domain, something (never fully identified, possibly Cloudflare's own bot/threat handling or some edge behavior specific to query strings on that exact path) silently swallowed those requests. They never even showed up in the Worker's own request log. The fix was to stop using a query string entirely and give logout its own dedicated path instead. If anything like that ever resurfaces, that's the pattern to reach for: a real path, not a query parameter, on protected routes.

Cloudflare Workers run JavaScript, but it's not the same JavaScript environment as the site's front-end. It executes in Cloudflare's own V8 isolate runtime at the edge, closer to a lightweight serverless function than a Node.js server. I edit and deploy this Worker directly through the Cloudflare dashboard's built-in code editor.

## 3. Hosting: GitHub Pages

The actual website files, all the HTML, CSS, JavaScript, images, and PDFs, are hosted for free by GitHub Pages. There's a `CNAME` file in the root of the repo containing just `alabamasmd.org`, which is what tells GitHub Pages to serve the site under my custom domain instead of the default `github.io` address.

GitHub Pages serves static files through Fastly's CDN. That's worth knowing because it explains a specific quirk I've run into more than once: after pushing a change, the live site can keep showing the old version for a few minutes even after the push succeeds, because Fastly is still serving a cached copy. A hard refresh (or just waiting a couple of minutes) clears it. It's not a bug in my code, it's just CDN propagation delay.

There's no build step here. GitHub Pages serves the HTML/CSS/JS files exactly as they exist in the repo. Nothing gets compiled, bundled, or transpiled.

## 4. Source control: GitHub

The code lives in a GitHub repository at `github.com/SDALMayflower/alabamasmd.org`. I keep a local working copy of it in a folder on my computer. For a while that folder was also synced through OneDrive, and that combination (a git repo living inside a OneDrive-synced folder) caused real friction: OneDrive's own sync process would occasionally lock git's internal files (`index.lock`, `HEAD.lock`) right when git was trying to write to them, which made commits fail until the lock file was manually deleted. As of August 2, 2026, I unlinked OneDrive from this PC entirely, so that friction is gone. The folder path still says "OneDrive" in it out of habit (unlinking doesn't rename anything), but nothing syncs through it anymore.

There's a single branch, `main`. Every push to `main` is what GitHub Pages serves. There's no staging environment and no CI/CD pipeline, what's on `main` is what's live, immediately (modulo the Fastly caching delay mentioned above).

## 5. The site itself: plain HTML, CSS, and JavaScript

This is a static, hand-built site. No React, no Vue, no site generator, no framework of any kind, and no package.json, meaning there's no dependency list or build tooling at all. Every page is its own standalone `.html` file.

**HTML** structures every page. There are around 30 top-level pages (home, About Us, Our History, Membership, Events, News, and so on), plus a `members-only/` subfolder holding the password-protected pages (the membership directory, bylaws, meeting materials, minutes, email archive, and the lineage review form).

**CSS** handles all the visual styling, spread across four stylesheets in the `css/` folder: `main.css` (the bulk of it, site-wide layout, colors, the header/nav, buttons, and so on), `compact-page.css` (styling shared by simpler content pages), `logo-fix.css` (a small targeted fix for logo display), and `useful-links.css` (styling specific to that one page). Two Google Fonts are pulled in through CSS `@import` statements: Playfair Display (the serif headline font) and Inter (the body/UI font), plus a decorative display font called Ultra used sparingly.

**JavaScript** is a single file, `js/main.js`, plain vanilla JavaScript with no libraries or frameworks. It handles the mobile hamburger menu toggle, the header's scroll-triggered style change, smooth-scrolling for anchor links, fade-in animations as elements scroll into view, and the animated stats counters on the homepage.

## 6. Other moving pieces

**The contact and lineage forms** don't run through my own backend at all, they post to Formspree. See the dedicated Email section below for exactly how that's wired up.

**Analytics** run through Google Analytics (gtag.js), tied to a Google Analytics property with measurement ID `G-8R17FZY7H9`, embedded near the top of every page.

**Search visibility** is handled the standard way: a `robots.txt` file tells search engines not to index certain pages (old drafts, the member-docs folder, a few abandoned pages I never cleaned up), and a `sitemap.xml` lists the pages I do want indexed. There's also a Google Search Console verification file (`google70b5c9cfdfcd2462.html`) sitting in the root, which just proves to Google that I own the domain.

**Media files** (event promo videos, flyers, PDFs) live directly in the repo alongside the HTML. They're not stored anywhere else, no separate media server or CDN bucket, just committed straight into GitHub like any other file.

## 7. Email

There's no email server or mailbox hosting tied to alabamasmd.org directly, no Google Workspace, no GoDaddy email hosting, nothing like that. Every form on the site sends mail through Formspree, a third-party form-to-email relay, under my Formspree account (barrettleeholt@gmail.com). There are two separate forms set up there:

- **Contact Form** (Formspree form ID `xzdngbkb`) handles the general Contact Us form on `contact_submit.html`.
- **Lineage Submission Form** (Formspree form ID `xkodrrnd`) handles two different forms on the site that both feed into it: the public lineage submission form on `membership.html`, and the supplemental lineage review form on the members-only `lineage-form.html`.

I checked both forms' settings directly in the Formspree dashboard rather than assuming, and confirmed both are configured to deliver to `smdal.info@gmail.com`, the Society's shared inbox. My personal address, `barrettleeholt@gmail.com`, is also "linked" and verified on the Formspree account itself, but that only means it's an allowed account-level address, not that it receives anything. Every live form on the site, contact and lineage alike, lands at the shared Society inbox, not my personal one.

Formspree also handles basic spam protection on the Contact Form through a honeypot field, and the account is on Formspree's free plan, capped at 50 submissions a month across every form combined. If that limit ever becomes a real constraint (a big lineage-submission push around a conference, for instance), that's the number to watch.

If I ever need to change where submissions land, I don't touch any code. I just open the relevant form in the Formspree dashboard, go to its Email action under Workflow, and update the target address there.

## 8. How a change actually goes live, start to finish

1. I edit a file locally (HTML, CSS, JS, or the Cloudflare Worker script).
2. For anything other than the Worker, I commit the change with git and push it to the `main` branch on GitHub.
3. GitHub Pages picks up the push and republishes automatically, no manual deploy step. It's usually live within a minute or two, though Fastly's CDN cache can occasionally hold onto the old version a bit longer, as mentioned above.
4. For changes to the Cloudflare Worker specifically, GitHub isn't involved at all. I paste the updated script directly into the Cloudflare dashboard's code editor and click Deploy. That takes effect immediately.
5. Cloudflare sits in front of all of it the whole time, but for anything that isn't one of the protected members-only paths, it just passes the request straight through to GitHub Pages without touching it.

## 9. Things worth remembering later

A few loose ends and quirks that aren't obvious from just reading the code:

There's a folder called `documemts` (yes, misspelled) sitting in the repo root alongside the real `member-docs` folder. It holds a duplicate copy of the membership directory PDF and appears to be a leftover from an earlier version of the file structure. I searched every HTML, CSS, and JavaScript file in the repo and confirmed nothing on the live site links to it or any file inside it. It's dead weight, safe to delete.

The site has no database and no user accounts in the traditional sense. Access to the members-only section is controlled by a single shared password, not individual logins, verified against a hash stored in a session cookie rather than any server-side session store.

If I ever move off GitHub Pages or Cloudflare, the two things that would need to be rebuilt elsewhere are the DNS/domain configuration and the Worker's password-gate logic, everything else (the HTML/CSS/JS files themselves) would port over to almost any static host with no changes needed.
