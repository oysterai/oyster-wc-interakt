# AGENTS.md

The full working guide for this repo. [CLAUDE.md](CLAUDE.md) is a pointer to
this file; everything lives here so there is one copy to keep correct.

## What this is

**Oyster WhatsApp for WooCommerce** is a WordPress plugin that delivers a
shopper's skin scan results and product recommendations over WhatsApp, through
the merchant's own [Interakt](https://www.interakt.shop) account.

It is an add-on. It does no scanning, stores no results, and renders nothing on
the storefront. It listens for the action hooks fired by **Oyster for
WooCommerce**, fetches the detail those hooks point at, and forwards it to
Interakt. The merchant designs the message templates and automations in their
own Interakt dashboard, in their own words.

Requires the Oyster for WooCommerce plugin, connected, and WooCommerce itself.
WooCommerce is checked even though no WooCommerce API is called here, because
Action Scheduler ships inside it and every queued send goes through it. The
Oyster plugin cannot stand in for that check: it still loads when WooCommerce is
absent, defining its version constant while never booting.

**Both are checked at runtime, not declared in a `Requires Plugins` header.**
That header resolves on the installed folder name rather than the plugin's
identity, so a dependency installed from a zip (a GitHub download names its
folder `-main`) reads as absent however active it is. WordPress then refuses
activation permanently, telling the merchant to install something they already
have. A runtime failure that names what is missing is recoverable; that is not.

## Architecture

```
Oyster backend  ──signed webhook──▶  Oyster for WooCommerce
                                            │
                                            │ do_action
                                            ▼
                                     this plugin (listener)
                                            │
                                            │ Action Scheduler
                                            ▼
                                     background job
                                     ├── reads scan detail from Oyster's API
                                     └── POSTs to api.interakt.ai
```

Two hooks are listened for: one when a scan completes, one when its product
recommendations are ready. Each is handled independently; the merchant decides
in Interakt whether that becomes one message or two.

### Never call Interakt from the hook handler

The handler's only job is to enqueue an Action Scheduler job and return.

The webhook receiver upstream has a short timeout and disables an endpoint
after a run of consecutive failures. Calling a third-party API inline puts that
API's latency inside the receiver's budget, so an Interakt outage or rate limit
would trip that breaker and disable the merchant's **entire** Oyster webhook
integration, not just WhatsApp. Keep the two failure domains apart.

### Delivery is not gated on marketing consent

A scan result is value the shopper already asked for. Delivering it over
WhatsApp instead of email is a change of channel for something expected, not a
marketing send, so this plugin does not withhold it.

Consent flags travel as traits so the merchant can segment on them for anything
promotional they build on top. They inform; they do not block. Opt-in for
template messages is enforced by WhatsApp at the merchant's own business
account level, and the merchant is the data controller.

## Coding conventions

- `declare( strict_types=1 );` in every PHP file.
- WordPress coding style: tabs for indentation, snake_case method/function
  names (not camelCase, which is a WordPress convention rather than an oversight;
  linters that flag it against generic PSR rules are wrong for this repo).
- No Composer, no npm build step.
- Readable class names, method signatures and variable names do the
  explaining. Reach for a better name before reaching for a comment.
- **Comments explain *why*, not *what*.** Never narrate what a block does or
  how it does it, because that is what reading the code is for. Do explain a
  non-obvious constraint, an invariant, or a specific bug the code works
  around.
- **Default to no comments.** Add one only when removing it would leave a
  future reader confused. Prose-heavy comment blocks are not wanted here.
- Do not restate in a comment anything already stated in `readme.txt` or in
  the plugin's own settings copy. Two copies of the same fact drift, and the
  stale one is the one someone trusts.

## Security invariants (do not relax these)

- **The Interakt API key is merchant-supplied and secret.** Store it
  encrypted. Never log it, never echo it into an admin notice or a settings
  page value attribute, never include it in a support export or a bug report.
- **This plugin holds no Oyster credential.** Scans are read through the
  `oyster_woocommerce_api_get` filter, so the store's key stays with the plugin
  that owns it and dies with the connection. Never read or decrypt that option
  directly, and never add a second Oyster key to the settings screen.
- **Customer phone numbers and names leave the site on this path.** Send the
  minimum a template needs. Do not widen the payload because a field happened
  to be available.
- **Do not send skin analysis detail as traits.** Scores, concern severities
  and raw analysis stay out. The scan detail is reachable by the merchant
  through their own dashboard; it does not belong in a marketing tool's user
  timeline.
- Report links are signed and short-lived by construction. Never cache one,
  persist one, or write one to a log.
- Sanitise on input, escape on output, nonce every form, capability-check
  every admin action. Standard WordPress rules, no exceptions for admin-only
  screens.

## Public repo hygiene

**THIS REPO IS PUBLIC. Everything you write here is world-readable, and on
GitHub most of it cannot be fully unpublished afterwards.**

This applies to **every surface, not just code comments**. The equivalent
rule has been broken in a sibling repo via a PR description, so treat all of
these as publishing:

- code comments and docblocks
- commit messages
- **PR titles, PR descriptions, and PR/issue comments**
- `readme.txt` (including the changelog), `AGENTS.md`, `TESTING.md`
- release notes and tag messages

The rule: never assert facts about, or name the internal structure of, other
Oyster repos/products a public reader can't access, such as the backend API's
codebase, the vendor dashboard, the widget SDK's repos. Concretely, never
write:

- another Oyster repo's name, or a PR/issue/commit reference in one
- internal class, trait, table, column, file or endpoint-handler names from
  those repos
- internal infrastructure details (what the other side is hosted on, how it
  signs things, what its secrets are called)
- internal team process, ticket ids, or roadmap/timeline specifics

It's fine, and often necessary, to describe *behavior* of the backend Oyster
talks to ("the backend emits a signed event when a scan completes," "report
links expire shortly after they are issued"). Say what a merchant or
contributor can observe, not how it's built.

Naming **Oyster for WooCommerce** is fine: it is a published plugin a reader
can install. Naming what is inside it, or inside anything private, is not.

Cross-repo coordination still has to be expressible: describe it in terms of
*this* repo. "The API side of this ships separately on Oyster's own release
cycle; this plugin release should go out after it" says everything a reader
needs without naming anything private.

### A PR description is a document, not a message

Write it for someone reading the repo history in a year, not for the reviewer
reading it today. That means:

- no one addressed directly, no "as we discussed", no "@someone"
- no narration of what you tried, abandoned, or debugged along the way
- no live status ("tests running", "will push a fix shortly")
- no coordination instructions ("merge this first", "hold until Friday")

Status and coordination belong in the team channel. The PR gets the change:
what it does, why, and how to verify it.

## Third-party disclosure

This plugin connects to a service the merchant must be told about, and
WordPress.org requires the disclosure. `readme.txt` must carry:

- an **External services** section naming Interakt and `api.interakt.ai`,
  stating exactly what data leaves the site and when, and linking Interakt's
  privacy policy and terms of service
- a **Privacy** section stating plainly that a shopper's phone number and name
  are transmitted to Interakt, and which events cause it

Update both whenever the payload changes. A disclosure that understates what
is sent is worse than none.

## Git workflow

Branch from `dev`, never commit to it directly. `feature/*` and `bugfix/*`
target `dev`; `hotfix/*` targets `main`.

Commits are scoped and conventional (`feat(whatsapp): ...`). Batch related
work into one PR with several scoped commits rather than one PR per commit.

## Testing

Unit tests for anything with logic worth trusting on its own: payload shaping,
phone formatting, trait sanitising (Interakt rejects newlines, tabs and runs of
three or more spaces in trait values).

Integration tests under wp-env for the hook wiring and the queued job, with the
Interakt HTTP call stubbed. Never let a test suite make a live call to
`api.interakt.ai`.

A green suite is not proof the harness works. Before trusting a new test,
break the thing it covers on purpose and confirm it goes red.
