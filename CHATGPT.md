You’re basically describing an **internal CMDB-lite + team journal + change awareness** tool, optimized for *speed, low friction, and trust*. Laravel + SQLite on an internal OEL8 VM is a very reasonable way to get there fast, especially for a 10–20 person sysadmin team.

Below is a practical blueprint that stays KISS, but sets you up to grow.

---

## What you’re really building

A **Single Source of Truth (SSOT)** for “machine reality + team intent”:

* **Reality**: what a host is, where it lives, what it runs, what it depends on, current reachability/health signals, discovery outputs.
* **Intent / narrative**: what humans did, why they did it, what they learned, and when—attached to the machine with an audit trail.

If you nail *“fast entry + fast retrieval + reliable diff + notifications”*, comms will improve immediately.

---

## Minimal data model that scales without getting fancy

Keep the domain tight. You can add fields forever, but these entities get you most of the value:

### 1) `machines`

Core identity + a few “indexable” attributes.

* `id`
* `hostname` (unique)
* `fqdn`
* `ip_primary`
* `env` (prod/dev/test)
* `location` / `segment`
* `os` / `os_version`
* `owner_team` / `service_owner`
* `criticality` (1–5)
* `status` (active/decommissioned/unknown)
* `tags` (optional JSON)
* timestamps

### 2) `machine_notes`

This is the “team chat but attached to the asset”.

* `machine_id`
* `author`
* `note_type` (handoff, incident, config, maintenance, FYI)
* `body` (markdown/plain)
* `created_at`

### 3) `machine_events` (audit log)

Tracks changes to machine records + system actions.

* `machine_id`
* `actor` (user/service)
* `event_type` (field_change, discovery_run, import, sn_sync)
* `diff` (JSON: before/after)
* `created_at`

### 4) `discoveries` (optional, but useful)

Store “looksie outputs” in a controlled way.

* `machine_id`
* `method` (ssh/dig/snmp/etc)
* `summary` (short fields for search)
* `raw` (text/blob)
* `created_at`

**Why this works:** it cleanly separates “facts” from “conversation” from “audit”. And it gives you the mechanism needed for notifications (events).

---

## The “team chat” experience without building Slack

If the main need is comms, don’t try to replicate chat UX. Make the app the **system of record** and let Teams handle the chatter via **notifications**.

### What to implement in-app (fast)

* Machine page shows:

  * header (facts)
  * **Notes feed** (reverse chronological)
  * “Changes” tab (audit trail)
  * “Discovery” tab (latest results + history)
* Notes support:

  * `@mentions` (simple parsing to link to users; even if you don’t DM, it helps)
  * “Pinned” note per machine (current state / gotchas)
  * note templates (handoff / maintenance / incident)

### What to offload to Teams

* When a note is added or a machine changes → post to a Teams channel.
* Use a consistent format so it becomes readable “activity stream”.

This gives you “team chat” where your team already lives, but keeps the **truth** inside your Laravel app.

---

## Teams integration (practical options in a segmented environment)

Most secure-to-least-friction path tends to be:

### Option A: Incoming Webhook to a Teams channel (lowest friction)

* App posts JSON to the webhook URL.
* Great for “notify channel about changes”.
* Works well if outbound HTTPS to Teams is allowed from your segmented network.

### Option B: Power Automate “When a webhook is received” (more governance)

* Your app posts to an internal endpoint; Power Automate relays into Teams.
* Useful if you want IT-controlled flows, approvals, or transformations.

### Option C: Microsoft Graph (most control, highest overhead)

* App authenticates (app registration), posts messages, can do richer interactions.
* Often harder in strict environments due to auth/certs/network controls.

**MVP recommendation:** start with **Option A** (incoming webhook), and design your notification layer so you can swap transport later.

**Notification rules that matter:**

* Note created
* Critical fields changed (owner, env, IP, status, criticality)
* Discovery indicates drift (new open ports, hostname mismatch, etc.)

---

## Auth: keep it simple, but compatible with AD realities

You don’t need perfect auth day one; you need “good enough internal only”.

Practical patterns:

### Phase 1 (immediate)

* Keep OAuth-protected behind your existing internal reverse proxy / SSO gateway if you have it.
* Or use Laravel auth with **local users** mapped to AD usernames (no passwords stored if you can avoid it).

### Phase 2 (near-term)

* **OIDC/SAML to AAD/ADFS** (common enterprise path) via a proxy or Laravel Socialite provider.
* If pure on-prem AD is the only option, sometimes you end up with LDAP auth—still workable, but coordinate with whoever owns identity.

**Key point:** design your tables to store `user_principal_name` / `samaccountname` and display name. Don’t hard-bind to one IdP detail.

---

## SQLite feasibility for 10–20 people

SQLite is a good choice **if you respect its constraints**:

**What it’s great at**

* Single VM, internal tool, moderate write volume.
* Simple backups (copy the file), easy export.
* Very low ops burden.

**Where it can bite**

* Lots of concurrent writes (it locks at the database level).
* “Chatty” workloads where many people post notes at once.

**Reality check:** for 10–20 sysadmins, if usage is mostly reads and occasional notes/updates, SQLite will likely be fine. To keep it fine:

* Avoid “every pageview writes something” patterns.
* Queue discovery jobs and write results in batches.
* Add indexes on `hostname`, `ip_primary`, `machine_id` foreign keys, and timestamps.

**Exit plan:** keep your schema and Eloquent models compatible with Postgres/MySQL from day one. Laravel makes this easy—don’t use SQLite-only hacks.

---

## Deployment on OEL8 in a strict environment (fast + maintainable)

Avoid `php artisan serve` for anything beyond personal dev. For your dev VM, do:

* **nginx + php-fpm** (standard, predictable)
* `systemd` services for:

  * php-fpm
  * nginx
  * queue worker (`php artisan queue:work`)
  * scheduler (`php artisan schedule:run` via cron or systemd timer)

### “KISS but solid” layout

* Put the SQLite DB under something like: `/var/lib/linux-onprem-webserver-tools/database.sqlite`
* Lock down permissions to the php-fpm user
* Nightly backup: copy the file + app `.env` (secrets handled appropriately) to a protected location

---

## ServiceNow integration without turning this into a giant project

Treat ServiceNow as a *data source*, not your primary store.

**MVP integration pattern**

* On machine view: “Lookup in ServiceNow” button that queries by hostname/IP.
* Optional nightly sync job that populates a small set of fields:

  * CI sys_id
  * assignment group
  * support contact
  * lifecycle status
* Store the SN identifiers so you don’t re-discover identity each time.

**Do not** try to mirror the entire SN CMDB. You’ll lose time and trust.

---

## Discovery via SSH / dig: make it safe + repeatable

You already have a prototype; make it operationally safe:

* Use a **dedicated restricted service account** and locked-down key handling.
* Implement discovery as queued jobs with:

  * timeouts
  * per-host rate limits
  * captured stdout/stderr
  * checksum or summary extraction (ports, hostkeys, OS hints)
* Store raw output in `discoveries.raw`, but extract a few fields for search.

This becomes a powerful “what changed?” assistant when paired with event diffing.

---

## The MVP you can deploy fast

If you want the fastest path to ship this:

1. Machines list with strong search/filter (hostname, IP, env, owner, tag)
2. Machine detail page
3. Notes feed (with author + timestamp)
4. Audit trail (field changes + note creation)
5. Teams webhook notifications for:

   * note added
   * critical machine field changed
6. Basic roles:

   * viewer
   * editor
   * admin
7. Export:

   * “Export all machines + notes” (CSV/JSON)
   * “Export single machine dossier” (JSON or printable HTML)

That’s enough to change behavior and create a comms backbone.

---

## The next layer that adds value

After MVP, the highest leverage features are:

* **Watch / Subscribe**: users can “watch” machines or tags; Teams notifications become relevant.
* **Change requests / planned work**: lightweight “maintenance record” with start/end + notes.
* **Diff views**: “show me what changed in the last 7 days” across all machines.
* **Linking**: reference another machine, a ticket, a KB doc inside notes (`MACHINE:host123`, `INC:12345`).
* **Dashboards**: “unknown owner”, “stale discovery”, “high criticality without notes in 90d”.

---

## Why this will help your situation

Because it creates:

* a **shared operational picture** (machines + context)
* a **repeatable narrative** of what happened (notes + diff)
* **forced visibility** (Teams posts)
* and **low-friction contribution** (add a note in 20 seconds)


If you already have MariaDB available, I’d use it—especially because your “team chat + audit trail” workload is *write-y* in bursts (notes/events), and MariaDB removes SQLite’s single-writer bottleneck without adding much complexity.

## Recommendation

### Use MariaDB now (primary), keep SQLite as an export/portable snapshot

* **MariaDB**: system of record, supports concurrent writes cleanly, easier to grow into “real” reporting, fewer weird locking edge cases.
* **SQLite**: treat as a *deliverable format* (“export the SSOT and walk it to another enclave”), not the live DB.

That gives you both: maintainability *and* your “flat-file exportability” goal.

## Decision rubric (fast)

Use **MariaDB** if any of these are true (you’re already close):

* 10–20 people actively adding notes during incidents/maintenance windows
* you want richer filtering/reporting and don’t want to baby indexes/locks
* you’ll add background jobs (discovery, ServiceNow sync) that write frequently

Use **SQLite** live only if:

* usage is mostly read-only, notes are rare, and you want the absolute lowest ops footprint

## Architecture options

### Option A: Use an existing shared MariaDB (best if it’s stable and supported)

Pros: backups/monitoring likely already exist.
Cons: you’re dependent on that platform’s lifecycle/permissions.

### Option B: Run your own MariaDB on the same VM (best “contained” approach)

Pros: you control schema, upgrades, backups; no cross-team dependency.
Cons: you own patching/backups.

For your situation (“regain control fast”), **Option B** is often the most practical.

## Minimal MariaDB setup choices that keep it simple

* Create a dedicated DB + user for the app.
* Use `utf8mb4`.
* Turn on slow query log early (even in dev) so you don’t get surprised later.
* Backups:

  * nightly `mysqldump` **and** (optionally) physical backup if your org standard supports it
  * keep 7–14 days locally + push to whatever internal secured backup target is allowed

## Laravel-specific guidance

* Stick to standard Laravel migrations (avoid DB-specific features).
* Put “chat/audit” tables on MariaDB:

  * `machine_notes`
  * `machine_events`
  * `discoveries` (if you store raw output, consider compressing or storing as file with path in DB)

### One small schema tip that pays off

Add indexes now:

* `machines.hostname` (unique)
* `machines.ip_primary`
* `machine_notes.machine_id`, `machine_notes.created_at`
* `machine_events.machine_id`, `machine_events.created_at`

## Preserving the “export anywhere instantly” property

You can still hit your portability goals even with MariaDB:

### Provide 2 export modes

1. **Portable SQLite snapshot export**

   * A command like: `php artisan ssot:export-sqlite`
   * It creates a `ssot_export_YYYYMMDD.sqlite` containing machines + notes + events
   * This can be copied to any machine and opened with any SQLite browser

2. **CSV/JSON exports**

   * Machines export
   * Machine dossier export (machines + notes + recent events)

This keeps your “flat file” workflow intact without forcing SQLite to be the live concurrency layer.

## Bottom line

Given your emphasis on “team chat” and rapid operational comms: **run MariaDB (existing or local)** for the live app, and implement **SQLite export** as a first-class feature.

If you tell me which route you’re leaning toward (existing shared MariaDB vs local MariaDB on the VM), I’ll lay out the exact minimal deployment pattern (systemd unit choices, backup commands, and the Laravel `.env`/DB config) that fits an OEL8 locked-down environment.
