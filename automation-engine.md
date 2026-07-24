# Automation Engine

> The Automation Engine is the platform's event orchestration service.
> Modules publish events but never invoke cross-cutting concerns directly.
> Platform subscribers perform infrastructure tasks such as indexing and
> activity recording. The workflow runtime executes user-defined
> automations. This separation keeps modules independent, makes behavior
> observable, and allows new features to participate in automation
> without modifying existing modules.

## Workflow shape

v1 ships one workflow shape:

```
CONDITION → ACTION × N → END
```

No branches, no loops, no delays, no human approval, no parallelism.
A rule is a chain: one condition, one or more actions, one end.

## Three-layer model

```
┌──────────────────────────────────────────────────────────────────┐
│ Layer 1 — DISPATCHER (the spine)                                  │
│   App\Automation\AutomationDispatcher                             │
│   One method: dispatch(type, userId, payload, key, occurredAt,    │
│                           origin, metadata)                      │
│   Producers call this. Engine does not know producers exist.     │
├──────────────────────────────────────────────────────────────────┤
│ Layer 2 — PLATFORM SUBSCRIBERS (infrastructure)                   │
│   App\Automation\Subscribers\PlatformSubscriber                   │
│   Synchronous. Always run, regardless of user rules.              │
│   Examples: search re-index, audit/activity, metrics.             │
├──────────────────────────────────────────────────────────────────┤
│ Layer 3 — WORKFLOW RUNTIME (user automations)                     │
│   App\Workflow\Runtime\WorkflowRuntime                            │
│   Async, idempotent. Compiles each rule into a linear workflow   │
│   and executes it: CONDITION → ACTION × N → END.                 │
└──────────────────────────────────────────────────────────────────┘
```

The three layers never call each other directly. The dispatcher runs
subscribers and enqueues the runtime. The runtime never reaches back.

## File map

```
app/Automation/
├── EventDispatcher.php                 interface producer depends on
├── AutomationDispatcher.php            subscribers + enqueue EvaluateAutomations
├── Actions/
│   ├── ActionHandler.php               interface (concrete side effects)
│   ├── ActionRegistry.php
│   ├── CreateNotificationAction.php
│   ├── TagItemAction.php
│   ├── MarkStarredAction.php
│   └── SaveBookmarkAction.php
├── Events/
│   ├── AutomationEvent.php             type + userId + payload + version + occurredAt + key + origin + metadata
│   ├── AutomationEventRegistry.php     dotted types + descriptions + wildcard matcher
│   └── EventDescriptor.php
├── Resolvers/
│   ├── EventContextResolver.php        interface: event → flat context dict
│   └── RssEventContextResolver.php     RSS implementation
└── Subscribers/
    ├── PlatformSubscriber.php          interface (search, audit, metrics)
    ├── SubscriberRegistry.php
    └── RssDefaultSubscriber.php        RSS: search re-index + audit

app/Workflow/                            ← user-automation layer
├── ActivityType.php                    enum: TRIGGER, CONDITION, ACTION, END
├── Activity.php                        immutable activity record
├── Workflow.php                        immutable compiled workflow
├── Expression/
│   └── ConditionEvaluator.php          structured-JSON condition over a context dict
├── Execution/
│   └── ExecutionContext.php            event + rule + workflow + activity + context
├── Steps/
│   ├── Step.php                        interface (run takes ExecutionContext)
│   ├── StepResult.php                  ok / fail
│   ├── StepRegistry.php                ActivityType → Step
│   ├── ActionStep.php                  wraps an ActionHandler
│   ├── ConditionStep.php               evaluates the rule's conditions
│   └── EndStep.php                     terminates
├── Compiler/
│   └── RuleCompiler.php                AutomationRule → Workflow (linear)
└── Runtime/
    └── WorkflowRuntime.php             Workflow → execution log

app/Models/
├── AutomationRule.php
├── AutomationRuleExecution.php
└── WorkflowTemplate.php                predefined rule templates users can clone

app/Jobs/Automation/
├── EvaluateAutomations.php
└── Subscribers/
    ├── IndexItemForSearch.php
    └── RecordActivity.php

app/Http/Controllers/Automation/
├── RuleController.php                  rule CRUD
├── RuleExecutionController.php         logs + replay
└── TemplateController.php              browse + apply workflow templates
```

## Strict naming conventions

Names are part of the contract. Don't deviate.

| Concept       | Class / Field                | Naming pattern                              | Example                                   |
|---------------|------------------------------|--------------------------------------------|-------------------------------------------|
| Event type    | string                       | `source.subject.verb` lowercase dotted      | `rss.item.created`, `rss.item.*`           |
| Origin        | string                       | one of the `AutomationEvent::ORIGIN_*` set | `web`, `scheduler`, `automation`, `replay`|
| Subscriber    | class name                   | `<Scope>DefaultSubscriber`                  | `RssDefaultSubscriber`                     |
| Activity id   | string                       | `{slug}.{kind}.{seq}` 1-based              | `rule.42.condition.1`, `rule.42.action.2` |
| Workflow name | string                       | `rule.<id>` or user-defined                 | `rule.42`                                 |
| Step kind     | `ActivityType` enum case     | UPPER_SNAKE                                 | `ACTION`, `CONDITION`, `END`              |
| Step class    | `<Kind>Step`                 | PascalCase + Step                           | `ActionStep`, `ConditionStep`             |
| Action kind   | string                       | `verb_object` lowercase snake               | `create_notification`, `mark_starred`     |
| Condition key | string                       | `<field>_contains` / equality              | `title_contains`, `feed_id`               |

Adding a new kind requires all six: enum case, class, registry entry,
order number, test, and doc update. The compiler + step registry will
fail loudly if you skip any of them.

## Activity ordering (strict)

`ActivityType::order()` returns the canonical position. The compiler and
the runtime both rely on it; the rule below MUST hold.

| Order | Kind       | Notes                                                |
|-------|------------|------------------------------------------------------|
| 10    | `TRIGGER`  | Belongs to the dispatcher, not the workflow body.    |
| 20    | `CONDITION`| Every rule-workflow has exactly one.                 |
| 40    | `ACTION`   | Wraps an `ActionHandler`.                            |
| 90    | `END`      | Terminal.                                            |

The kind `TRIGGER` is reserved for the dispatcher. `END` is the only
terminal kind. The compiler rejects any other kind — adding a new kind
is a multi-file change that requires updating ActivityType, StepRegistry,
the runtime, and the docs (in that order).

## Event shape

| field        | type     | notes                                                       |
|--------------|----------|-------------------------------------------------------------|
| `type`       | string   | dotted, lowercase, `source.subject.verb`                    |
| `version`    | int      | always `1` today                                            |
| `userId`     | int      | owning user (system events use `0`)                         |
| `payload`    | array    | **business data** — small, identifiers only                |
| `occurredAt` | Carbon   | when it happened (default: now)                             |
| `key`        | string?  | override; otherwise derived from `(type, item_id, …)`        |
| `origin`     | string   | `web` / `scheduler` / `automation` / `mcp` / `api` / `cli` / `replay` |
| `metadata`   | array    | **infrastructure** — correlation_id, request_id, retry count |

**Payload discipline:** pass identifiers (`item_id`, `feed_id`), not full
records. Resolvers hydrate the rest from the database.

**Metadata vs payload:** metadata is for infrastructure (correlation ids,
request ids, queue/retry state); payload is for business data.

**Key:** idempotency anchor. Replays with the same key never double-fire.
Manual replays append `replay:<id>:<ts>` so they count as fresh arrivals.

## Event types (current)

Registered in `RssServiceProvider`:

```
rss.feed.fetched   — scheduled RSS feed fetch completed (new or 304)
rss.item.created   — a new RSS article was discovered
rss.item.read      — a user marked an RSS article as read
rss.item.starred   — a user starred an RSS article
```

Dotted convention; wildcards work in rule `trigger_event` (e.g. `rss.item.*`).

## Scopes (current)

| scope       | `user_id` | `group_id` | audience                  |
|-------------|-----------|------------|---------------------------|
| `personal`  | required  | null       | the owner only            |
| `team`      | required  | required   | every user in the group   |
| `system`    | null      | null       | every user                |

`RuleController` only writes `personal` rules from the UI. `team` and
`system` are schema-supported but not yet exposed.

## Conditions (current)

The condition object is a flat JSON dict AND-reduced by
`App\Workflow\Expression\ConditionEvaluator`.

| key                     | matches when                                |
|-------------------------|---------------------------------------------|
| `title_contains`        | substring of item title (case-insensitive)  |
| `excerpt_contains`      | substring of item excerpt                   |
| `author_contains`       | substring of item author                    |
| `url_contains`          | substring of item URL                       |
| `guid_contains`         | substring of item GUID                      |
| `feed_title_contains`   | substring of feed title                     |
| `feed_url_contains`     | substring of feed URL                       |
| `feed_folder_contains`  | substring of feed folder                    |
| `feed_id`               | equal to RssItem.feed_id                    |
| `user_id`               | equal to event user_id                      |
| `<any>_contains`        | generic substring over every context string |

Unknown keys log a warning and match as no-ops — a malformed rule never
crashes a dispatch.

The context dict is produced by `RssEventContextResolver` from the event
payload + the `RssItem` + its `RssFeed`. It includes:

```
item_title, item_excerpt, item_author, item_url, item_guid, item_published_at,
feed_id, feed_title, feed_url, feed_folder,
event_type, event_origin, event_version, occurred_at
```

## Actions (current)

```json
[
  { "type": "mark_starred", "data": {} },
  { "type": "create_notification", "data": { "priority": "high" } },
  { "type": "save_bookmark", "data": { "category": "Research" } },
  { "type": "tag_item", "data": { "tags": ["security", "laravel"] } }
]
```

| type                  | effect                                            | idempotent via                              |
|-----------------------|---------------------------------------------------|---------------------------------------------|
| `create_notification` | one unread `Notification` row                     | `(user, type, source, unread)` lookup       |
| `tag_item`            | attach tags to the source file                    | pivot-table upsert                          |
| `mark_starred`        | `is_starred = true`, re-dispatches `rss.item.starred` | pre-check `is_starred`                  |
| `save_bookmark`       | upsert a `Bookmark` (URL is the key)              | pre-check by `(owner_id, url)`              |

**Activity vs action.** Activities are runtime nodes; actions are the
side-effect handlers they wrap. Today every user-rule activity is an
`ACTION`; the `CONDITION` and `END` kinds exist to wrap the rule's
conditions and terminate execution.

**Action execution is independent, not transactional.** The runtime runs
the chain; a failure logs + marks the rule `failed` but does not abort
the chain. Other rules on the same event are unaffected.

## Step kind behaviours (current)

| Kind       | Behaviour                                                                              |
|------------|-----------------------------------------------------------------------------------------|
| `CONDITION`| Reads `data.condition`; ok → next; fail → rule marked `skipped`.                        |
| `ACTION`   | Looks up the named `ActionHandler` in `ActionRegistry`; calls `execute(rule, data, ctx)`. |
| `END`      | Always ok; the runtime stops here.                                                     |

## Step status taxonomy (current)

`StepResult::STATUS_*` — the runtime maps these to the rule-execution
record:

| Status | Recorded rule status | Meaning                                       |
|--------|----------------------|-----------------------------------------------|
| `ok`   | `matched`            | step completed                                |
| `fail` | `failed` (or `skipped` for `CONDITION`) | step errored; or condition didn't match |

## Compiler example (current)

Rule JSON in `automation_rules.actions_json`:

```json
{ "type": "create_notification", "data": { "priority": "high" } }
```

Compiled by `RuleCompiler` into a Workflow:

```
rule.42.condition.1   (CONDITION)  ← rule.conditions_json
        ↓
rule.42.action.1      (ACTION)     ← rule.actions_json[0]  (wraps create_notification)
        ↓
rule.42.end.1         (END)
```

A rule is just the smallest possible workflow: one condition, one or
more actions, one end. The compiler is linear — no branching, no graph
analysis, no cycle detection beyond a step budget.

## ExecutionContext (current)

The single object every `Step::run` receives. v1 carries only what the
linear pipeline needs:

```
ExecutionContext
├── event      AutomationEvent (read-only)
├── rule       AutomationRule (read-only)
├── workflow   Workflow (read-only)
├── activity   Activity the step is running (replaced per step)
└── context    array<string, mixed>  the resolver-hydrated event context
```

Steps are pure functions over the context. v1 has no mutable
cross-step state.

## Platform subscribers (current)

`RssDefaultSubscriber` matches the `rss.*` pattern. On every `rss.item.*`
event it dispatches `IndexItemForSearch`. On every `rss.item.created` it
also dispatches `RecordActivity`. Both jobs are queued; the subscriber
itself is synchronous but cheap.

## Manual replay

`POST /rss/rules/logs/{id}/replay` constructs a fresh event from a stored
execution row and re-runs every rule that matches. The dispatcher sets
`origin = "replay"` and overrides the key with `replay:<id>:<ts>` so
downstream actions re-fire instead of being deduped. The logs UI shows a
`Replay` button on every row.

## Workflow templates

`GET /rss/rules/templates` lists every row in `workflow_templates`.
`POST /rss/rules/templates/{id}/apply` materializes a template as a
personal `AutomationRule` for the current user (via
`WorkflowTemplate::toRule($userId, $name)`).

The seeder ships three starter templates:
- `star-security-posts` — star articles with "security" in the title
- `high-priority-laravel-news` — star + notify on Laravel security posts
- `save-research-to-bookmarks` — bookmark anything tagged "research"

## Observability

| Log key                              | when                                  |
|--------------------------------------|---------------------------------------|
| `automation.event.received`          | dispatcher enqueued the event         |
| `automation.subscriber.rss`          | RSS default subscriber ran             |
| `automation.subscriber.failed`       | a subscriber threw                    |
| `automation.compile.failed`          | rule → workflow compile threw         |
| `workflow.step.threw`                | a step threw (caught, marked failed)  |
| `automation.action.failed`           | action-level failure                  |
| `automation.rule.failed`             | rule-level failure                    |
| `automation.condition.unknown_key`   | evaluator ignored a non-key           |
| `rss.item.create.skipped`            | parser produced a duplicate guid      |
| `rss.refresh_feed.failed`            | job's exception path                  |

Durable surfaces:

- `audit_logs.action = 'rss.item.created'` — activity stream.
- `automation_rule_executions` — rule log; filterable at
  `GET /rss/rules/logs?rule=<id>&status=matched|skipped|failed`.

## Wiring (current)

- `app/Providers/AppServiceProvider.php` singleton-binds the engine
  itself (`AutomationEventRegistry`, `SubscriberRegistry`,
  `ActionRegistry`, `ConditionEvaluator`, `EventDispatcher`).
- `app/Providers/RssServiceProvider.php` registers the four `rss.*` event
  types and `RssDefaultSubscriber`. Future modules add their own provider
  the same way.
- `bootstrap/providers.php` loads both providers.
- `routes/console.php` schedules `rss:refresh` hourly.
- `app/Http/Middleware/HandleInertiaRequests.php` injects
  `notifications.{unread_count, recent}` for the navbar bell.
- `database/seeders/DatabaseSeeder.php` calls
  `WorkflowTemplateSeeder` so a fresh install ships starter templates.

## Adding a new event source

1. **Create a provider** (`FooServiceProvider`) that, in `register()`,
   uses `resolving(AutomationEventRegistry::class)` to add the dotted
   types, and `resolving(SubscriberRegistry::class)` to add the
   `FooDefaultSubscriber`. Register the provider in
   `bootstrap/providers.php`.
2. **Implement a resolver** (optional): `FooEventContextResolver implements
   EventContextResolver`. Bind it in `AppServiceProvider` or your own
   provider.
3. **Dispatch from the producer:** call
   `app(EventDispatcher::class)->dispatch('foo.bar.created', $user->id, ['id' => $id], key: 'foo:bar:'.$id, origin: AutomationEvent::ORIGIN_WEB)`.

No engine changes required.

## Adding a new action

```php
class SendWebhookAction implements ActionHandler
{
    public function type(): string { return 'send_webhook'; }

    public function execute(AutomationRule $rule, array $data, array $context): void
    {
        // MUST be idempotent.
    }
}
```

Register it in `ActionRegistry::__construct()`. The rule editor's action
dropdown updates automatically. The `ACTION` activity that wraps it
needs no new code — `ActionStep` looks up handlers by name.

## Adding a new platform subscriber

```php
class MetricsSubscriber implements PlatformSubscriber
{
    public function subscribesTo(): string { return '*'; } // all events
    public function handle(AutomationEvent $event): void { /* … */ }
}
```

Register in your module's `FooServiceProvider`. Subscribers run
synchronously inside the dispatch — they must be cheap. Heavy work
belongs in a queue job that the subscriber itself dispatches.

## Adding a workflow template

Insert a row into `workflow_templates` (or add it to
`WorkflowTemplateSeeder`):

```php
WorkflowTemplate::create([
    'slug' => 'my-template',
    'name' => 'My template',
    'description' => '...',
    'icon' => 'lightning-charge-fill',
    'event_namespace' => 'rss.item.created',
    'event_version' => '1',
    'conditions_json' => ['title_contains' => '...'],
    'actions_json' => [
        ['type' => 'mark_starred', 'data' => []],
    ],
    'sort_order' => 0,
]);
```

Users see it at `/rss/rules/templates` and clone it into a personal rule
via the `apply` action.
