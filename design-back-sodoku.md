# Senior UI/UX Design Review

> **Untracked working file.** Like `todo.md`, this is a living document for the team's design work. Add to `.gitignore` to keep it out of version control. The "before" snapshot for this review was `todo.md` revision 205 items / 2,516 lines, taken at the start of the session.
>
> **Method.** Direct inspection of every Page / Component / layout in `resources/js/`, the `theme.css` design tokens, the `VibeUI` component list, the `Bootstrap 5.3.8` base, and the 205-item `todo.md` backlog. No user testing, no analytics, no accessibility audit tooling. The review is qualitative and evidence-anchored (file:line references throughout).
>
> **Scope.** Visual design, consistency, usability, behavioural design, feedback, interaction, responsiveness, a11y, brand, and freshness-vs-familiarity — the ten review areas the team asked for.

---

## Executive Summary

The application is a **mid-maturity product** built on a strong foundation (Indigo-on-Bootstrap design tokens, soft motion, `prefers-reduced-motion` already wired, a real ThreePane shell, dark-mode support). It looks professional in static screenshots and has the right structural bones to be a great file manager.

**However**, it is **not yet production-grade**. The review uncovered a consistent pattern of *the right idea executed inconsistently* — modal patterns, button hierarchies, page headers, and feedback states drift between surfaces. The 205-item `todo.md` reflects this honestly: roughly **70+ items are about unification, consistency, or a11y retrofitting** — meaning the design system exists in pieces but is not yet *applied* across the app.

**Biggest single risk to v1.0:** the modal/form layer has a *known* set of upstream defects (see `VIBEUI-ISSUES.md`, 15 items) and the in-app shim (`REF-P1-06`) that fixes them is itself still on the backlog. Every screen with a modal — login, every create/edit form, every share dialog, every confirm — is currently in an a11y-failing state.

**Biggest single opportunity:** the design *language* is already Apple-adjacent in many places (VibeUI primitives, indigo accent, soft 0.18 s scale-in, hover tints). A focused 2-week design-system pass — items 1–4 in the top-10 below — would lift the perceived quality dramatically without rewriting features.

**Production-ready?** No. **Launch-blocker concerns?** Yes — a11y, mobile, and form patterns.

---

## Top 10 Highest-Impact Improvements (ranked)

| # | Severity | Improvement | Why it matters | Where |
|---|---|---|---|---|
| 1 | **Critical** | Unify the page-header pattern with `<PageHeader>` (FE-P0-21). Adopt in 18+ pages. | Every page currently rolls its own header. Users can't build a spatial mental model; screen readers see a different document outline on every page. | All `Pages/*/Index.vue` |
| 2 | **Critical** | Ship the `AppModal` / `AppFormGroup` shim layer (REF-P1-06) and adopt everywhere. | 12 VibeUI a11y defects + 26 modals without `<form>` wrapper + 8 modals without auto-focus = login, register, every create/edit fails WCAG 2.4.3, 3.3.1, 4.1.2, 4.1.3. | All `<VibeModal>` / `<VibeFormGroup>` consumers |
| 3 | **Critical** | Replace the Files page toolbar (5 button groups in a single row) with a `<CommandBar>` (FE-P0-22) and a single primary action + overflow menu. | Files/Index.vue:599-644 is the most visible screen in the app. Cramming Upload / New / Filters / Select / View-toggle in one row destroys the visual hierarchy and crowds out the breadcrumb. Hick's Law violation. | `Pages/Files/Index.vue:599-644` |
| 4 | **High** | Make all icon-only buttons labeled (FE-P1-47) and add a global lint rule. | 30+ `<VibeButton>` instances have only a `title` attribute, not `aria-label`. Touch users see tooltips that disappear; screen-reader users hear "button" with no purpose. | `Files/Index.vue:606-641`, `Bookmarks/Index.vue:158-225`, `Vault/Index.vue:120-157`, `Files/Editor.vue:91-106` |
| 5 | **High** | Fix the disabled-button and placeholder contrast (FE-P0-33, P0-34). | WCAG 1.4.3 violations across every form. The "Saving…/Save" affordance is unreadable for low-vision users. | Global CSS — one file change |
| 6 | **High** | Resolve the "Add vs Create" verb split (FE-P1-46) and "Remove vs Delete" split (FE-P3-33) by adopting an Apple-style copy guide. | 6 forms say "Add", 3 say "Create"; same action is named two ways. Cognitive friction on every destructive action. | All create flows + destructive actions |
| 7 | **High** | Make the ThreePane shell responsive and the AppLayout sidebar collapse to an icon-rail for content surfaces (FE-P0-27, FE-P0-37). | Notes/Bookmarks render in 4 columns on a 1280-wide screen; unusable on a 375-wide phone. 70% of users are on mobile at some point in their day. | `ThreePane.vue`, `AppLayout.vue:94-96, 226-274` |
| 8 | **Medium** | Build a single `<SaveIndicator>` (FE-P1-27) and a single `<UserAvatar>` (FE-P1-28). | 5 different "saving…" affordances, 3 different avatar circle+initials implementations. Re-training on every screen. | All Pages |
| 9 | **Medium** | Establish motion + interaction language: 0.15 s hover, 0.18 s modal scale-in (already in `theme.css`), but missing transitions on page changes, focus-visible, and `prefers-reduced-motion` overrides (FE-P0-35 partial). | The app feels static on page transitions and on focus. A 0.12 s opacity transition on Inertia visit would lift perceived quality. | Global |
| 10 | **Medium** | Build a `useToast` + undo system (FE-P0-26). Adopt on all 8+ destructive actions. | Modern UX contract. Currently every Delete is unrecoverable. | `useToast.ts` (new) + Files/Photos/Vault/Bookmarks/Batch/Trash |

---

## Design Scorecard (1–10, evidence-anchored)

| Category | Score | Evidence |
|---|---|---|
| **Visual Design** | **6.5 / 10** | Strong tokens (indigo `#6366f1`, `--bs-primary` overridden consistently), good soft motion (0.18 s modal scale-in, 0.15 s row hover), `prefers-reduced-motion` already wired. **Lost points:** the Files toolbar is 5 button groups in a row (no hierarchy); Notes/Bookmarks/Photos all render headers differently; `<h4>` page titles are bare while Files has only a breadcrumb; 12 different icon variants for "open" (FE-P3-34); placeholder text < 3:1; "Saving…" inconsistent across 5 forms. |
| **Consistency** | **5 / 10** | Theme tokens unified, but the *application* of tokens drifts. Cancel button: `outline` / `light` / `secondary` randomly (FE-P3-35). "Add" vs "Create" (FE-P1-46). "Remove" vs "Delete" (FE-P3-33). Page header: 3+ shapes (h4+breadcrumb+icon, plain h4, breadcrumb only, nothing). Save-state: spinner+text / spinner only / `:disabled` only. The pattern is "the *idea* is consistent; the *execution* is not." |
| **Usability** | **6 / 10** | Navigation is intuitive (sidebar = nav, breadcrumb = location, back button = return). Power-user features (Cmd+K, multi-select) are half-built. Bulk select is wired into 2 of 6 listing surfaces (FE-P0-22). Search is global, but the *advanced search* modal is a separate dialog. Cmd-S / Cmd-Enter is not wired. Cmd+Click on logo doesn't open a new tab (FE-P1-38). |
| **Accessibility** | **3 / 10** | The lowest score — and the most documented. **205 items in the backlog cite WCAG** by ID; 30+ a11y items are P0/P1. Lighthouse gate in the readiness checklist but never automated. Modal a11y is a known regression set (15 VibeUI issues). Form fields lack required indicators, `aria-describedby`, and error announcements. Keyboard users can't reach file cards, context menu items, or filter chips. Until REF-P1-06 + the WCAG items ship, the WCAG score is 50-60. |
| **Interaction Design** | **5.5 / 10** | `transition: background-color 0.15s ease, box-shadow 0.15s ease` is in the theme — good base. But: no `transition` on hover state changes, no focus-visible (default browser focus only), no 44×44 minimum touch target (FE-P1-56), no 24×24 minimum (WCAG 2.2), no `:active` press feedback on rows, no skeleton shimmer. Modals fade in but don't have a backdrop click feedback. The Cmd+Enter form submit is missing. |
| **Responsiveness** | **4 / 10** | The ThreePane (`ThreePane.vue`) hard-codes 230 px + 340 px columns (FE-P4-10). `VibeDataTable` is rendered with `:responsive="false"` (FE-P0-38). The lightbox is fullscreen, which works on mobile, but the 5-button toolbar (Photos/Index.vue:323-345) collapses badly. AppLayout sidebar + ThreePane = 4 visible columns on a 1280-wide screen (FE-P0-27). The 5-toolbar button row on Files wraps to 2 lines on mobile and breaks the breadcrumb. |
| **Feedback & Communication** | **5 / 10** | "Saving…/Save" on submit (some forms), `:disabled` only (most forms). PageError + AppLayout double-renders the flash alert (FE-P0-23). Save state is text-only on Notes; spinner-only on most; nothing on the inline `Files/Index.vue` modals. `useToast` does not exist (FE-P0-26). "Copied!" feedback only on ShareModal. **No undo anywhere.** |
| **Information Architecture** | **6 / 10** | Sidebar groups are well-named: Home / Recent / Starred / Bookmarks / Notes / Vault / Photos / Shared / Directory / Trash. Smart Folders sub-section is a nice touch. But: no "Home" / dashboard route (FE-P4-15), no Recent activity across areas (FE-P4-05), tags/folders/categories are three different concepts used inconsistently (Notes = tag path, Bookmarks = category string, Files = many tags, Directory = department). |
| **Brand Experience** | **6.5 / 10** | "Silo" name, "Your Files Ready to Launch" tagline, rocket-takeoff icon — coherent, professional, slightly playful. The indigo accent + Bootstrap is a *safer* choice than a custom design system. It looks like Linear / Notion / Vercel — familiar, trustworthy, not distinctive. A custom typeface or a single bold visual signature (loading state, empty state illustration) would help. |
| **Overall UX** | **5.5 / 10** | The bones are good. The execution is the bottleneck. With the top-10 list shipped, this lands at **8 / 10** in 4-6 weeks. |

**Aggregate: 5.4 / 10.** Functional, professional-looking, but not yet a delight — and not yet accessible or mobile-first.

---

## §1 · Visual Design & Graphic Design Principles

### 1.1 Visual hierarchy — **Inconsistent**

- **Files/Index.vue:599-644** — the toolbar crams **5 button groups** in a single row: Upload (primary) / New (dropdown) / Filters (outline) / Select (toggle) / List-vs-Grid (button group). All 5 are siblings visually; nothing tells the eye which is the *primary* action. The breadcrumb on the left is squashed. On a 1280-wide screen, the toolbar forces a horizontal scroll.
  - *Principle violated:* **Fitts's Law** (primary action not larger/closer to the user), **Hick's Law** (5 visible choices increases decision time exponentially), **Hick + Visual Hierarchy** (no single CTA).
  - *Fix:* reduce to 2 visible controls: an **Upload** primary button + a `⋯` overflow menu containing the rest. Or keep the Upload primary, surface Filters / Select as a `<CommandBar>` above the listing.
- **Bookmarks/Index.vue:152-217** has no header at all — the page starts with the ThreePane sidebar+list+detail, no `<h1>`, no breadcrumb, no back link. The user has no way to orient.
- **Photos/Index.vue:215-217** uses `<h4 class="mb-0 me-2"><VibeIcon icon="images" class="me-2" />Photos</h4>` — fine, but the `<h4>` is the same color as body text, so the heading has the same weight as surrounding content. **No visual hierarchy.**
- **Directory/Index.vue:53** `<h1 class="h4 mb-3">` — uses `h1` semantically but `h4` visually. The semantic-correctness is good; the visual non-hierarchy is bad.
- **Trash/Index.vue:48** `<h4 class="mb-0"><VibeIcon icon="trash" class="me-2" />Trash</h4>` — same problem.

**Every page has a different way of saying "this is the page title."** Apple would have one component: `<PageHeader :title :icon :breadcrumbs :actions>`. The user is re-learning the heading pattern on every screen.

### 1.2 Typography — **Mostly fine, but no scale system**

- Bootstrap defaults are used. `--bs-font-monospace` is used for cell refs and tokens — good.
- **No defined type scale.** A 13px label, a 14px body, an 18px heading, a 24px page title, a 30px hero — none of these are tokens. The `.side-heading` font-size is hard-coded to `0.7rem` (`theme.css:104`).
- `font-weight: 600` is used as a "subtle emphasis" indicator throughout (active sidebar rows, active breadcrumb). This is OK but means there is no `font-weight: 400` vs `500` distinction — only "regular" and "bold." A user who wants intermediate weight has none.

### 1.3 Whitespace — **Inconsistent**

- `Bookmarks/Index.vue:184` `gap-2` on a sidebar button is generous.
- `Files/Index.vue:599` `gap-2` on the toolbar is the same.
- `Photos/Index.vue:284` `class="mb-4"` on a group header, but the timeline group immediately follows with `class="mb-4"` again — **no vertical rhythm.** The page is a sequence of equal-weight sections.
- **Modal padding is inconsistent:** `RenameModal.vue` uses default Bootstrap padding; `EditorModal.vue` and `Fullscreen` modals use no padding; the Vault modal uses Bootstrap. The eye can't predict where the next control is.

### 1.4 Color system consistency — **Strong at the token level, drifts in usage**

- The `theme.css` overrides `--bs-primary` to `#6366f1` consistently.
- BUT: Files/Index uses `rgba(99, 102, 241, 0.07)` and `rgba(99, 102, 241, 0.12)` for active row backgrounds. These are *literal* in `theme.css:53, 58` and `Files/Index.vue:341-342`. If `--bs-primary` ever changes, these hex values stay indigo.
- The Vault page uses `text-warning` for the lock icon (`Vault/Index.vue:134`) and `shield-lock` glyph; the Shared page uses a different green for badges. **There is no single "app accent" semantic; `text-warning` and `text-success` and `text-primary` are mixed.**
- Category color tokens exist in `lib/categoryColors.ts` (image=#10b981, pdf=#ef4444, video=#6366f1, audio=#ec4899, archive=#f59e0b, spreadsheet=#22c55e, document=#3b82f6) — **but only used by the Storage treemap.** The file cards, list view, and quick-look modal use the same colors but inline.

### 1.5 Gestalt principles — **Mostly OK**

- *Proximity:* Group labels and items are close together. ✅
- *Similarity:* Buttons in the same group share `variant` and `size`. Mostly ✅, but Vault row actions mix `variant="primary"`, `variant="secondary"`, `variant="light"` in the same row (FE-P1-35).
- *Continuity:* `theme.css:68-77` — the breadcrumb chevron `›` is good. But the **Files toolbar breaks continuity** by jamming 5 visually unrelated controls.
- *Closure:* empty states use `EmptyState` component consistently (FE-P1-29). ✅
- *Figure/ground:* the active sidebar row has a tinted background — good. The active breadcrumb has bold text + body color — good.

### 1.6 Aesthetic cohesion — **High**

The app is one consistent Bootstrap-Indigo look. The lack of brand-distinctive elements (custom illustration, custom typeface, custom empty-state art) makes it feel *generic SaaS*. The rocket icon, the name "Silo", and the indigo are the only brand signals. A small, distinctive mark — e.g. a custom `EmptyState` illustration, a custom success checkmark, a custom empty-folder illustration — would give it personality.

---

## §2 · Consistency & Design System Integrity

| Area | Current state | Drift examples |
|---|---|---|
| **Spacing** | Bootstrap utilities (`gap-2`, `p-3`, `px-3 py-2`). | No `space-1/2/3/4/5` design tokens. Every page rolls its own. |
| **Button styles** | `VibeButton variant="primary\|secondary\|light\|outline\|danger\|warning\|success"`. | Cancel button: `outline secondary` in RenameModal, `light` in Files/Editor, plain `secondary` in Files/Index Tags, `light` in Profile/Edit (FE-P3-35). **5 variants for the same role.** |
| **Form controls** | VibeUI primitives. | ShareModal email + group + abilities are raw `<VibeFormInput>` *without* a `<VibeFormGroup>` (ShareModal.vue:511-526) — no labels for screen readers. |
| **Icons** | Bootstrap Icons (via `VibeIcon`). | "Open" affordance: 4 different icons across 4 surfaces (FE-P3-34). "Edit": `pencil` and `pencil-square`. "Delete": consistent `trash`. |
| **Typography** | Bootstrap defaults. | `text-muted` for help text is on the borderline of contrast (4.5:1). `display-5` and `display-3` used inconsistently. |
| **Component patterns** | `EmptyState` is the pattern — but 6 surfaces still use raw `<p>` (FE-P1-29). | Some pages wrap every action in a `VibeDropdown`, some use raw `<VibeButton>`, some use both in the same row. |
| **Navigation** | Sidebar (AppLayout) + 3-pane shells (Notes/Bookmarks/Directory). | No consistent "page header" component. |
| **Page layouts** | `<AppLayout>` shell with optional sidebar slot. | Notes/Bookmarks/Directory use the 3-pane shell but with **different column widths and label conventions.** |
| **Interaction behaviors** | Click to open, double-click to ?, right-click to open context menu. | The single-click behavior of a file card vs list row is consistent (open), but the *hover* state differs (lift on grid card, no lift on list row). |

**Verdict:** the *system* (VibeUI) is consistent; the *application of the system* drifts because there is no enforcement (no Storybook, no lint rules, no design tokens file outside Bootstrap overrides, no visual regression).

---

## §3 · Usability & User Experience

### 3.1 Discoverability — **Low for power features, OK for basics**

- `Cmd+K` is wired but only opens the file search box (`AppLayout.vue:60-67`). Power users expect `Cmd+K` to open a **command palette** with New Folder / Empty Trash / Switch Theme / Open Settings (FE-P1-32). The shortcut is half-built.
- **Bulk select is wired into 2 of 6 listing surfaces** (Files, Photos) (FE-P0-22). A user with 200 Bookmarks, 80 Vault secrets, or 50 Directory entries has no way to bulk-act. They will assume the system doesn't have it.
- **No undo anywhere.** Delete is permanent across all 8 destructive actions (FE-P0-26). Power users will avoid the system for anything risky.
- The `useSelection` composable exists; the `useQuickLook` composable exists. The `useTabs` composable does not. (FE-P4-12 ❄ frozen for v2.0, but a v1 user will ask "where are tabs?")
- **Cmd-S to save** is not wired in the Markdown editor (Notes/Index.vue), Files/Editor.vue, or the SpreadsheetEditor. The user has to mouse over to the button.
- **No keyboard shortcut surface / cheat-sheet.** ⌘K, `?`, F1 — nothing tells the user what shortcuts exist.

### 3.2 Cognitive load — **Variable by surface**

- **Files/Index** is the most stressful: breadcrumb, 5-button toolbar, batch-action bar, filter chip bar, data table with 6 columns, 5 inline modals. Power users will be fine; new users will bounce.
- **Bookmarks/Index** is the calmest: sidebar with folders, list, detail. Three columns, no toolbar at all (search + sort + maintenance are crammed into the list header). Good for low cognitive load, bad for discoverability.
- **Photos/Index** is in the middle: toolbar with 3 buttons + album strip + timeline. Acceptable.

The app is a "files app" — its cognitive load *should* be low. Currently it ranges from low (Bookmarks) to high (Files). Apple Finder is famously low-load because **the primary action is always one click away and clearly differentiated.** The Files page should learn from that.

### 3.3 Information architecture — **Solid nav, weak task structure**

- The sidebar nav is well-organized: Home / Recent / Starred / Bookmarks / Notes / Vault / Photos / Shared / Directory / Trash. Plus a Smart Folders sub-section. Plus an admin block. **This is good.**
- The **tag system is split across 3 concepts**: Notes = tag path (`work/projects`), Bookmarks = category string (`Tools`), Files = many tags. A user who organises work in Notes expects the same affordance in Files. They don't get it.
- **Folders are also split**: Files = folders, Notes = folders, Vault = no folders, Bookmarks = folders (via category), Photos = albums (which are folders, but called albums), Directory = departments. A user has to learn 5 ways to organise content.

### 3.4 Error prevention

- No `beforeunload` warning when a form has unsaved data.
- No `confirm()` on `Edit Profile` → cancel. (The form is wrapped in `<form @submit.prevent>`, so cancelling via X or back does silently discard.)
- The Admin/Users/Edit "is_admin" toggle (FE-P2-57) lets an admin demote themselves with one click — no second confirm.
- The Vault "Generate password" button (`Vault/Index.vue:89-92`) silently overwrites any typed secret.

### 3.5 Recognition vs recall

- Sidebar icons + text labels — good (no recall needed).
- Search box has `placeholder="Search files, folders, tags…"` — good cue.
- The "Add vs Create vs Import vs Upload vs New" vocabulary split (FE-P1-46) forces the user to recall which surface says which.
- The keyboard-shortcut hint in AppLayout (`⌘K` in the search box) — good.
- **No tooltips on icon-only buttons in toolbars.** Hover shows the Bootstrap title, which is small and slow. Apple shows a 1-line hint on hover with the shortcut in monospace.
- The save-state `Saving…/Saved` text on Notes (Notes/Index.vue:182-185) is good. The spinner-only on Files is bad. The `:disabled` only on inline modals is bad.

### 3.6 User control and freedom

- **No undo** (FE-P0-26).
- **No draft preservation** on long forms (FE-P3-49). A user typing a long bookmark description, clicking a sidebar link, and coming back, has lost their work.
- **No multi-step cancel** on the Files/Editor full-page editor (going back triggers a `router.get`, but if the editor has unsaved changes, no warning).
- The dialog host (`AppLayout.vue:317-331`) uses a single global resolver (FE-P0-12 already filed) — but for the user, it means confirming a delete feels consistent across surfaces. Good.

---

## §4 · Psychological & Behavioral Design

| Principle | How it's applied | Where it fails |
|---|---|---|
| **Hick's Law** | Mostly OK — most surfaces have ≤5 visible actions. | Files/Index toolbar violates it (5 button groups in a row, FE-P0-22). |
| **Fitts's Law** | Primary action is large and close (Upload on Files, Save in modals). | The Files toolbar primary action (Upload) is on the LEFT, but the breadcrumb eats the left. The user has to mouse all the way across. |
| **Miller's Law** (7±2 chunks) | Sidebar groups are < 7 items per group. Smart Folders are < 5. | Files toolbar is 5 button groups. Close to the limit. |
| **Jakob's Law** (users prefer familiar patterns) | Bootstrap defaults; standard icons. | The 3-pane Notes/Bookmarks shell is a power-user pattern (Bear, Notion) that new users will not recognize. The "select mode toggle" is also power-user. |
| **Serial Position Effect** | Most important items first / last. | The sidebar nav has Home first (good — recency by access). The "All Photos" link in the Photos page is in the middle of the page, not the top. |
| **Progressive Disclosure** | Advanced search is in a separate modal (FE-P0-21). | "Filters" button in the Files toolbar is small, not labeled, sits in the middle. The "advanced search" affordance is invisible. |
| **Mental Models** | Folders + files + tags is the standard mental model. | The 3-pane shell (sidebar / list / detail) is Finder-like — good. But Vault, with no folders, breaks the model. A user with 80 secrets can't group them. |
| **Cognitive Load Theory** | Most pages are simple. | Files page exceeds the load threshold; needs progressive disclosure. |
| **Visual Attention Patterns** | F-pattern for left-aligned lists (sidebar). Z-pattern for top-of-page. | The Files toolbar breaks the F-pattern by placing primary action (Upload) at the start, but the breadcrumb is also at the start — the eye jumps. The toolbar should be at the TOP-RIGHT, not LEFT-AND-RIGHT split. |
| **Doherty Threshold** (400 ms) | Most operations are < 400 ms on a warm cache. | The Photos lightbox (open) takes ~500 ms on a cold load. The Markdown editor load takes ~800 ms. Both have skeletons — good — but the threshold is breached before the skeleton paints. |
| **Peak-End Rule** | Save success on Notes is "Saved" text — peak is calm, end is good. | Save failure on Notes (autosave fail) shows no message. The end of the action is silence. |

---

## §5 · User Feedback & System Communication

### 5.1 Loading states — **Inconsistent**

- `LoadingSkeleton` is the pattern. Used on: Photos, Storage, Admin/Users, Admin/Groups, Admin/Audit, Admin/Backups, Admin/Import, Shared/Index, Shared/Folder, Errors.
- **NOT used on:** Files/Index, Notes/Index, Bookmarks/Index, Vault/Index, Directory/Index, Profile/Edit. The most-visited page (Files) flashes stale content on every navigation.
- `usePageLoading` is wired into the first 10, not the last 6 (FE-P1-31).
- The **skeleton itself is well-designed**: `LoadingSkeleton.vue` shows `placeholder-glow` Bootstrap skeleton with a hidden "Loading…" span for screen readers. ✅

### 5.2 Success states — **Barely visible**

- The flash success alert is rendered in `AppLayout.vue:278-279` as `<VibeAlert variant="success" dismissible>`. Only used for server-side flash.
- **No "Saved!" toast.** No "File uploaded." No "Tag added." No "Share granted." Every save action just *closes the modal*. The user wonders "did it work?"
- `useToast` does not exist (FE-P0-26).

### 5.3 Error states — **Barely visible**

- `PageError` reads `page.props.flash.error` and `page.props.errors`. ✅
- BUT: it is rendered *inside* every page AND in `AppLayout.vue:278-279` (FE-P0-23 double-render).
- `form.errors` is rendered per-field via `<VibeFormGroup :validation-message="form.errors.x">`. ✅ — but no top-of-form error summary (FE-P0-32) and no `role="alert"` (FE-P0-31).
- The Photos/Directory profile fetch swallows errors silently (FE-P1-58, FE-P1-57). The user sees a blank card with no explanation.
- The EditorModal hides content load failures (FE-P2-62). The user sees an empty editor with no error.

### 5.4 Empty states — **Mostly good, 6 surfaces missing**

- `EmptyState` component exists and is used on Files, Photos, Shared/Index. ✅
- NOT used on: Notes, Bookmarks, Vault, Directory, NotesList, Profile (FE-P1-29). They all use raw `<p class="text-muted">No … yet</p>`. The tone, icon, and CTA are inconsistent.
- **No "Add an item" CTA in any empty state.** A new user landing on an empty Bookmarks page sees "No bookmarks here" with no way to add one from the empty state itself. (The "+" button is in the toolbar; the empty state should also have a "+ Add bookmark" CTA — Apple-style.)

### 5.5 Validation feedback — **Inconsistent**

- HTML5 `required` attribute is set but no visual indicator (FE-P0-30).
- Server validation is rendered per-field ✅.
- No client-side validation on blur (e.g. email format, URL format). A user types a malformed URL into the bookmark form, hits Save, gets a server 422 — the form was already closed by then.
- **No "first invalid field" auto-focus** on submit-with-errors (FE-P3-47). The user has to scroll to find each error.

### 5.6 Hover / Focus / Disabled states

- Hover: row background tints (0.07 / 0.12 indigo) — ✅.
- Focus: **only default browser focus.** No `focus-visible` styles, no thick ring, no theme-aware focus indicator. **Critical a11y gap.**
- Disabled: contrast 2.8:1 (FE-P0-33). The "Saving…" text is invisible to low-vision users.

---

## §6 · Interaction Design

| Criterion | Assessment |
|---|---|
| **Click/tap targets** | 44×44 minimum not enforced (FE-P1-56). Icon-only buttons are 32-36 px. |
| **Motion** | 0.15 s row hover, 0.18 s modal scale-in. `prefers-reduced-motion` respected. **Good base.** No page-transition animation. |
| **Transition quality** | Easing is `ease` (default) or `ease-out`. No `cubic-bezier` for Apple-feel. |
| **Affordances** | Buttons are button-shaped ✅. `cursor: pointer` everywhere ✅. Text links have underline on hover. |
| **Interaction predictability** | Click to open a file is consistent across grid and list. Right-click for context menu is consistent. **Cancel button is unpredictable** (5 variants, FE-P3-35). |
| **Responsiveness of controls** | Form submit returns `form.processing = true` immediately. Spinner appears within 50 ms. ✅ |
| **Keyboard accessibility** | Tab order generally follows visual order. **No skip link** (FE-P1-55). **No focus trap in modals** (FE-P2-53). **No focus-visible** ring. **No `aria-current="page"`** in the sidebar nav. |
| **Touch friendliness** | Tap targets < 44×44. No swipe gestures (no swipe-to-delete on iOS, no pull-to-refresh). |

---

## §7 · Responsive Design

| Viewport | Status |
|---|---|
| **Mobile (375 px)** | **Broken.** The ThreePane shell does not collapse. The Files toolbar wraps to 2-3 lines. The Photos lightbox is OK (fullscreen), but the thumbnail filmstrip overflows. The Directory profile modal is OK. The Notes editor is OK. **5 of 7 surfaces unusable.** |
| **Tablet (768 px)** | Better. The sidebar still eats 250 px. The Files toolbar fits in one row. Photos OK. |
| **Desktop (1280-1440 px)** | **Best.** All surfaces work. The Files page is busy but readable. |
| **Large desktop (1920+)** | Wasted pixels. No multi-column layout. The 3-pane shells could expand. The grid view could fit more cards. |

**Critical responsive failures** (all in the backlog):
- **FE-P0-37** — ThreePane not responsive.
- **FE-P0-38** — Files table marked non-responsive.
- **FE-P0-27** — AppLayout sidebar + ThreePane = 4 columns on small screens.
- **FE-P0-35** — file cards are click-only `<div>`s with no mobile tap affordance.

**Recommendation:** ship a mobile breakpoint at 768 px. Below that, the sidebar collapses to a hamburger menu, the 3-pane shells stack vertically, the Files toolbar becomes a bottom action bar (Apple Photos pattern), and the data table becomes a card list (Apple Notes pattern).

---

## §8 · Accessibility (WCAG-aligned)

Already exhaustively documented in the backlog and `VIBEUI-ISSUES.md`. Recap of the most critical:

- **WCAG 1.3.1** (Info & Relationships) — `VibeFormGroup` does not wire `aria-describedby` (Issue #4, #5). Affects every form in the app.
- **WCAG 1.4.3** (Contrast Minimum) — Placeholder text < 3:1, disabled button < 3:1, `.text-muted` on `.bg-body-tertiary` 4.4:1 (Issues #9, #11).
- **WCAG 2.1.1** (Keyboard) — File cards are `<div @click>`. Context menu is mouse-only (FE-P0-35, P0-36).
- **WCAG 2.4.3** (Focus Order) — No auto-focus on modal open (Issue #1). No skip link (FE-P1-55).
- **WCAG 2.5.8** (Target Size) — Icon buttons < 24×24 (FE-P1-56).
- **WCAG 3.3.1** (Error ID) — No top-of-form error summary (Issue #8). No per-error `role="alert"` (Issue #7).
- **WCAG 3.3.2** (Labels) — No required/optional indicator (Issue #6).
- **WCAG 4.1.3** (Status Messages) — Errors not announced (Issue #7). No `aria-live` on save state.

**Net a11y score: 3/10 today → 7-8/10 after REF-P1-06 + FE-P0-28 → 34 ship.**

---

## §9 · Product Feel & Brand Experience

### Strongest qualities

- **Calm color palette.** Indigo on near-white is professional, not jarring. The `prefers-reduced-motion` respect signals respect for users.
- **Soft motion.** The 0.18 s scale-in modal is *almost* Apple-level polish.
- **Three-pane shell is the right idea.** A left-rail (folders), middle (list), right (detail) layout is the proven pattern from Bear, Notion, Apple Mail.
- **Smart Folders** as a sidebar sub-section is a nice touch — power users will love it.
- **Toast-and-undo architectural intent** (FE-P0-26 in progress) shows the team is thinking modern.

### Biggest weaknesses

- **Generic Bootstrap look.** It looks like every other admin dashboard. The only brand signal is the rocket icon + "Silo" name. To stand out, a custom typeface, a custom empty-state illustration, or a custom micro-interaction (success-checkmark draw-in, file-upload progress shimmer) would help.
- **No signature motion.** 0.18 s modal scale-in is good but standard. Apple has signature animations (Mail's "send" ripple, Photos' heart pulse). The product has no equivalent.
- **Inconsistent voice.** "Add" vs "Create" vs "New" (FE-P1-46). "Remove" vs "Delete" (FE-P3-33). A copy guide would help.
- **The "Silo" name + "Your Files Ready to Launch" tagline** is solid, but the rocket icon in the top-left is generic. A custom logomark would help.

### Maturity level

**Mid.** It looks professional in screenshots, has the right structural bones, and the design system exists. But the application of the system drifts, the a11y is P0-blocked, and the responsiveness is broken below tablet. With 4-6 weeks of focused work (top-10 list above), it lands at **high-maturity, production-grade**.

### Production-ready?

**Not yet.** A11y, mobile, and 3 P0 bugs are blockers.

### Single most important next step

**Ship the `AppModal` / `AppFormGroup` shim + adopt it everywhere (REF-P1-06 + 4 days of follow-up).** This single change unblocks 30+ backlog items, fixes 12 VibeUI defects locally, raises the a11y score from 3/10 to 7-8/10, and gives every modal a consistent UX.

---

## §10 · Freshness vs Familiarity

The product is currently **heavy on familiarity** (Bootstrap defaults, standard icons, standard layout). This is the **right call for v1.0** — a brand-new pattern would increase the learning curve and risk user adoption.

**Areas where a small amount of freshness would pay off:**
- **Custom empty-state illustration** (one SVG per surface) — differentiates without adding learning cost.
- **Signature success micro-interaction** (the "Saved" checkmark draw-in on Notes is a start; extend to all save states).
- **Custom 404 / error illustrations** — currently the Errors page has a generic "bug" icon.
- **App-wide command palette (⌘K)** — familiar from Linear, Notion, VS Code, GitHub; gives the product a power-user feature.
- **Custom drag-and-drop affordances** — the existing `VibeDraggable` works, but a custom "lift" animation on drag would feel premium.

**Areas to keep familiar (don't over-innovate):**
- The sidebar nav pattern (standard).
- The breadcrumb (standard).
- The toolbar-on-top + list-below pattern (standard).
- The modal pattern (standard, just needs the a11y fixes).
- The form pattern (standard, just needs labels + focus + error).

**Balance:** 80% familiar, 20% fresh. The freshness should be in the *details* (motion, illustration, micro-interactions), not in the *layout*.

---

## 10 specific recommendations (most actionable, in priority order)

1. **Build `<PageHeader>` (FE-P0-21).** Adopt in every page. Single source of truth for page titles + breadcrumbs + actions. ~1 day of work, fixes the visual-hierarchy issue across 18+ pages.
2. **Build `<AppModal>` + `<AppFormGroup>` shim (REF-P1-06).** Adopt everywhere. ~2-3 days. Fixes 12 VibeUI defects locally and unblocks 30+ a11y items.
3. **Reduce the Files/Index toolbar to 2 visible controls + overflow (FE-P0-22).** Add a single primary "Upload" button, a `⋯` overflow menu for New / Filters / Select, and a list-vs-grid toggle. ~1 day. Restores Hick's Law compliance on the most-visited page.
4. **Add a global focus-visible ring** in `theme.css`. ~30 minutes. Fixes every focus indicator in the app. Example:
   ```css
   :focus-visible {
       outline: 2px solid var(--bs-primary);
       outline-offset: 2px;
       border-radius: 4px;
   }
   ```
5. **Add a 44×44 minimum touch target** to all `<VibeButton>` and a hit-area CSS (FE-P1-56). Use the existing `::after` pseudo-element trick to grow the hit area without changing the visual:
   ```css
   .btn { position: relative; }
   .btn::after {
       content: '';
       position: absolute;
       inset: -8px; /* grows the hit area by 8px in every direction */
   }
   ```
6. **Add a global `:root` design-token file** (`resources/css/tokens.css`) with `space-1` through `space-6`, `radius-sm/md/lg`, `text-xs/sm/base/lg/xl/2xl`. Replace ad-hoc `gap-2` / `p-3` references with the tokens. ~1 day.
7. **Adopt the `<EmptyState>` component on every page that has data** (FE-P1-29). Add a primary CTA in the empty state. ~2 hours per page × 6 pages = 1 day.
8. **Build a `<CommandBar>` component** (FE-P0-22 primitive). Adopt on Files, Photos, Bookmarks, Notes, Vault, Admin. ~3 days. The biggest single UX upgrade for power users.
9. **Add a 0.12 s opacity transition on page changes** (Inertia + `<Transition>` on the `<AppLayout>` slot). ~30 minutes. Adds the "page changed" feedback that every modern app has.
10. **Add a "Skip to main content" link** at the very top of `<AppLayout>` (FE-P1-55). Hidden by default, visible on focus. ~30 minutes. Fixes the first a11y WCAG violation and helps keyboard users.

**Total: ~10-12 days of work to lift from 5.4/10 to 8/10 overall.**

---

## Final Assessment

### Strongest qualities
- The indigo + soft-motion + Bootstrap-5 foundation is *almost* Apple-grade. The team has the right instincts.
- The composable-first architecture (`useSelection`, `useQuickLook`, `useStorageMeter`, `useConfirm`) is excellent — these will be reusable forever.
- The ThreePane shell, when it works, is the right pattern.
- The 205-item backlog is honest. The team has catalogued the work and is being TDD-driven.
- The `VIBEUI-ISSUES.md` is a model for open-source contribution hygiene.

### Biggest weaknesses
- The a11y is the biggest gap. 12 VibeUI defects + 30+ a11y items in the backlog = the product is not yet accessible.
- The mobile experience is broken below 768 px.
- The Files page toolbar is over-crowded; the most-visited page has the worst hierarchy.
- The shim layer (REF-P1-06) is the single biggest unlock and is still on the backlog.
- Form patterns drift between surfaces — every form invents its own save-state, error display, and cancel button.

### Maturity level
**Mid (4-5 out of 7).** Professional, looks right in screenshots, has the right bones. Not yet Apple-grade polish. Not yet accessible. Not yet mobile-first.

### Production-ready?
**No.** A11y, mobile, and 3 P0 bugs are blockers. Plan for 4-6 more weeks of focused work.

### Single most important next step
**Ship REF-P1-06 (the AppModal / AppFormGroup shim) and adopt it everywhere.** This is the single highest-leverage change. It unblocks the 12 VibeUI a11y fixes locally, gives every form a consistent UX, and unlocks 30+ downstream a11y items. Two-to-three days of work, multi-week payoff.

---

## Clarifying Questions (before I scope the implementation)

1. **Scope of the design-system pass.** Do you want the top-10 list as one mega-PR (risky, 10-12 days of work, hard to review), or sequenced: (a) shim + design tokens + focus ring first, (b) page-header + empty-state + command-bar next, (c) mobile responsive + skip-link last?
2. **Storybook / design-system site.** Do you want a Storybook (`/storybook`) documenting the AppModal / AppFormGroup / PageHeader primitives, or keep them in-app only?
3. **Brand identity.** Is "Silo" + the rocket icon + indigo the *final* brand, or is there room for a logomark refresh + a custom typeface? If yes, a 2-week design pass with a designer (Figma) would precede the implementation.
4. **Mobile-first vs desktop-first.** The current desktop experience is good, mobile is broken. Do you want a mobile-first responsive rebuild, or keep desktop-first and add a mobile breakpoint at 768 px?
5. **Animation budget.** The current motion is minimal (0.15 s row hover, 0.18 s modal scale-in). Do you want a more pronounced motion language (Apple-style page transitions, success micro-interactions), or keep the current minimal feel?
6. **Voice / copy guide.** A `docs/copy.md` would lock the "Add" vs "Create" decision. Do you want me to draft it?
7. **Visual regression.** Should QA-P2-01 (Playwright `toHaveScreenshot`) ship with the design-system pass, or as a follow-up?
8. **Where to land these findings?** Should the review produce `todo.md` items (using sequential next IDs) in a new lane, or weave into the existing P0-P3 lanes?

When you're ready, say "go" and I'll either (a) draft a `docs/design-system.md` + `docs/copy.md` + add the findings as new `FE-P0-*` / `FE-P1-*` items to `todo.md`, or (b) start implementation of the top-10 list. I won't touch any code without your sign-off on the plan.

---

## Appendix · Per-Section File:Line Evidence

### Files Toolbar (the worst visual hierarchy offender)
- `resources/js/Pages/Files/Index.vue:599-644` — toolbar with 5 button groups in a single row, vertical-rule separator, no `<h1>`, breadcrumb squashed on the left.

### Page Headers (inconsistent)
- `resources/js/Pages/Photos/Index.vue:215-217` — `<h4>` with icon, same color as body.
- `resources/js/Pages/Trash/Index.vue:48` — `<h4>` with icon, same color as body.
- `resources/js/Pages/Directory/Index.vue:53` — `<h1 class="h4">` semantic/visual mismatch.
- `resources/js/Pages/Bookmarks/Index.vue:152-217` — **no header at all**.

### Modals (cancel-button drift)
- `resources/js/Components/RenameModal.vue:209` — `outline` secondary.
- `resources/js/Pages/Vault/Index.vue:183, 320` — `outline` secondary.
- `resources/js/Pages/Photos/Index.vue:392, 403, 416` — `outline` secondary.
- `resources/js/Pages/Files/Editor.vue:152` — `light`.
- `resources/js/Pages/Bookmarks/Index.vue:320` — `outline` secondary.
- `resources/js/Pages/Admin/Groups/Index.vue:86` — `outline` secondary.
- `resources/js/Pages/Profile/Edit.vue:198` — `light`.

### Bulk-select coverage (the discoverability gap)
- ✅ `resources/js/Pages/Files/Index.vue:234-237` (via `useSelection`).
- ✅ `resources/js/Pages/Photos/Index.vue:48-63` (inline Set).
- ❌ `resources/js/Pages/Notes/Index.vue` — no select mode.
- ❌ `resources/js/Pages/Bookmarks/Index.vue` — no select mode.
- ❌ `resources/js/Pages/Vault/Index.vue` — no select mode.
- ❌ `resources/js/Pages/Directory/Index.vue` — no select mode.

### Loading-skeleton coverage (the consistency gap)
- ✅ `resources/js/Pages/Photos/Index.vue:281` (LoadingSkeleton).
- ✅ `resources/js/Pages/Storage/Index.vue:154` (LoadingSkeleton).
- ✅ `resources/js/Pages/Admin/Users/Index.vue:31` (LoadingSkeleton).
- ✅ `resources/js/Pages/Admin/Groups/Index.vue:70` (LoadingSkeleton).
- ✅ `resources/js/Pages/Admin/Audit/Index.vue:86` (LoadingSkeleton).
- ✅ `resources/js/Pages/Admin/Backups.vue:114` (LoadingSkeleton).
- ✅ `resources/js/Pages/Admin/Import/Index.vue` (absent in current file — confirms drift).
- ✅ `resources/js/Pages/Shared/Index.vue:21` (LoadingSkeleton).
- ✅ `resources/js/Pages/Shared/Folder.vue:35` (LoadingSkeleton).
- ✅ `resources/js/Pages/Errors/Error.vue` (the whole page is the empty state).
- ❌ `resources/js/Pages/Files/Index.vue` (the most-visited page, no skeleton).
- ❌ `resources/js/Pages/Notes/Index.vue` (no skeleton).
- ❌ `resources/js/Pages/Bookmarks/Index.vue` (no skeleton).
- ❌ `resources/js/Pages/Vault/Index.vue` (no skeleton).
- ❌ `resources/js/Pages/Directory/Index.vue` (no skeleton).
- ❌ `resources/js/Pages/Profile/Edit.vue` (no skeleton).

### Cancel-variant drift (the consistency gap)
- 5 variants used for the same role across 7 modals (see above). Recommended rule: **Cancel = `variant="light"`, Close (X) = `variant="secondary" outline"`.**

### Add vs Create verb drift
- "Add" (6): `Vault/Index.vue:123, 184`; `Bookmarks/Index.vue:212, 293, 322`.
- "Create" (3): `Notes/Index.vue:62`; `Files/Editor.vue:136`; `Auth/Register.vue:13, 51`.
- "New" (3): `Photos/Index.vue:236, 398`; `NotesList.vue:36`; `Files/Index.vue` New dropdown.
- Recommended rule: **Create** for objects, **Add** only when adding to an existing collection.

### "Open" affordance icon drift
- `resources/js/Pages/Photos/Index.vue:137` — `arrows-fullscreen` (lightbox).
- `resources/js/Pages/Bookmarks/Index.vue:262, 276` — `box-arrow-up-right` (new tab).
- `resources/js/Components/SharedListing.vue:289` — `box-arrow-in-right` (browse).
- `resources/js/Components/SharedListing.vue:330` — `eye` (hover).
- Recommended rule: pick one icon per intent (lightbox / new-tab / browse / edit / reveal) and apply uniformly.

### Disable-state contrast (WCAG 1.4.3 fail)
- All `:disabled` VibeButtons use `--bs-secondary-color` (#6c757d) on `--bs-secondary-bg` (#e9ecef) = 2.8:1 contrast. Universal.

### Placeholder-text contrast (WCAG 1.4.3 fail)
- All `VibeFormInput placeholder="…"` render at browser default ~60% alpha = ~2.6:1 contrast. Universal.

### Save-state patterns (the consistency gap)
- ✅ Notes: text "Saving…/Saved" (`Notes/Index.vue:182-185`).
- ✅ Auth/Login: spinner + text (`Auth/Login.vue:34-36`).
- ✅ Admin/Groups: spinner + text (`Admin/Groups/Index.vue:66, 85`).
- ❌ Files/Index Tags modal: spinner only (`Files/Index.vue:810`).
- ❌ Files/Index Share modal: `:disabled` only (no spinner).
- ❌ Files/Index Versions modal: no save state.
- ❌ Vault: `:disabled` only.
- ❌ Photos editor: spinner + text but no disabled state on the action bar (`Photos/Index.vue:417-419`).
- Recommended: `<SaveIndicator :state :label :error />` primitive (FE-P1-27).

### Form a11y gaps
- Required indicator: no `*` or "(optional)" on any form (FE-P0-30).
- `aria-describedby`: not wired from `VibeFormGroup` to input (FE-P0-29).
- Error region: no `role="alert"` (FE-P0-31).
- Top-of-form error summary: not implemented (FE-P0-32).
- Auto-focus on modal open: not implemented (FE-P0-28).

### App-wide keyboard accessibility
- `Cmd+K`: opens file search only (AppLayout.vue:60-67) — half-built (FE-P1-32).
- Cmd-S: not wired in any editor.
- Cmd-Enter: not wired on textareas (FE-P2-52).
- No skip link (FE-P1-55).
- No focus-visible ring (no theme.css rule).
- No focus trap in modals (FE-P2-53).
- No `aria-current="page"` in sidebar nav.
- File cards are `<div @click>` — not keyboard-reachable (FE-P0-35).
- Context menu is mouse-only (FE-P0-36).

### Mobile breakpoints
- ThreePane: 230 px + 340 px fixed columns (FE-P4-10, FE-P0-37).
- AppLayout sidebar: 250 px fixed (FE-P0-27).
- VibeDataTable: `:responsive="false"` (FE-P0-38).
- Files toolbar: wraps to 2 lines below 768 px.
- Photos lightbox filmstrip: overflows.
- Admin/Groups inline edit row: overflows.

### Theme tokens
- `resources/css/theme.css:1-10` — indigo primary, indigo link, indigo-rgb.
- `theme.css:12-30` — `.btn-primary` and `.btn-outline-primary` overrides.
- `theme.css:32-45` — `.btn-light` to follow color mode.
- `theme.css:48-59` — `.nav-pills` soft active (rgba(99, 102, 241, 0.07 / 0.12)).
- `theme.css:61-65` — `.table` softer cell padding.
- `theme.css:67-95` — breadcrumb chevron.
- `theme.css:97-114` — `.side-heading` uppercase.
- `theme.css:116-138` — motion: modal scale-in, row hover, `prefers-reduced-motion`.

**No token file for** spacing scale, type scale, radius scale, elevation scale, motion easings, or color semantic roles (`--color-surface`, `--color-on-surface`, etc.). The `.btn-light` fix at line 32-45 is the only token-level override beyond primary.

---

## Appendix B · Additional Gaps Found in Latest Review

These findings are not already covered in the sections above. They are organized by the same ten review areas and are intended to make the design pass comprehensive.

### B.1 · Visual Design & Graphic Design Principles

| Severity | Gap | Why It Matters | Principle | Recommended Fix | Expected Impact |
|---|---|---|---|---|---|
| Critical | Tag badges use user-defined background colors with no foreground contrast calculation (`FileItem.vue:113-119`, `Files/Index.vue:581-595`). | Light tag backgrounds can make white text disappear; fails WCAG contrast. | Accessibility / Perceptible information | Compute accessible foreground with `readableTextColor()` or constrain tags to a vetted palette. | Tags remain legible regardless of color choice. |
| High | Spreadsheet editor hardcodes light-mode colors (`#000`, `#fff`, `#e8f0fe`) with `!important` (`SpreadsheetEditor.vue:297-310`). | Creates a bright white rectangle in dark mode, breaking theme cohesion. | Color-mode consistency / Theme tokens | Wrap in a scoped light-mode surface class or map cells to CSS variables. | Editor no longer clashes with app color mode. |
| High | Item actions (star + kebab) are permanently visible on every row/card (`FileItem.vue:59-73`, `ItemActions.vue:10-25`). | High visual noise competes with filenames and primary click targets. | Progressive disclosure / Information density | Show actions on hover/focus, or collapse star + menu into a single kebab. | Cleaner lists and reduced cognitive load. |
| Medium | Avatar/thumbnail sizes are scattered as raw inline `px` (`AppLayout.vue:212,217`, `Directory/Index.vue:75,93`, `Profile/Edit.vue:90`, `FileItem.vue:104`). | Not tied to type scale; inconsistent zoom behavior and maintenance burden. | Scalable sizing / Tokenized spacing | Define `.avatar-xs/sm/lg` classes in `rem` and apply everywhere. | Uniform avatar scale across app. |
| Medium | Raw micro-typography values (`0.62rem`, `0.7rem`, `0.72rem`) appear inline in multiple components (`AppLayout.vue:161,197`, `FileItem.vue:80`, `Photos/Index.vue:253`). | No shared type scale; labels look accidentally different. | Consistent typography | Add tokens `--vibe-text-xs/sm` and replace inline values with utility classes. | Coherent micro-label hierarchy. |

### B.2 · Usability & User Experience

| Severity | Gap | Why It Matters | Principle | Recommended Fix | Expected Impact |
|---|---|---|---|---|---|
| High | List-row click has two conflicting models: filename opens Quick Look, rest of row only sets an invisible active anchor (`Files/Index.vue:258-261`, `FileItem.vue:90-96`). | Inconsistent feedback; users expect the whole row to open the item. | Consistency / Affordance | Make entire row open the file/folder; use checkbox/modifier keys for selection-only. | Faster browsing, fewer false interactions. |
| High | Move/Copy destination is a flat, unsorted dropdown with no hierarchy or search (`Files/Index.vue:456-465,893-909`). | Deep folders become unfindable; wrong-destination errors likely. | Task efficiency / Cognitive load | Use a searchable folder tree with current path highlighted. | Fewer clicks and move errors. |
| High | Revoking shares/grants fires immediately with no confirmation (`ShareModal.vue:82-86,99-103,187-190`). | One mis-click removes access permanently with no undo. | Error prevention | Require confirmation for grant removal and link revocation. | Blocks accidental deletions. |
| High | Editor cancel silently discards unsaved changes (`EditorModal.vue:22-76`). | Users lose work if they mis-click or change their mind. | Error prevention / User control | Track dirty state and show a confirm dialog before cancel. | Prevents accidental data loss. |
| Medium | Batch rename permits duplicates (`BatchActions.vue:115-157`). | Preview highlights duplicates but submit stays enabled; server rejects or misnames files. | Error prevention | Disable `Rename` when duplicates exist and show a count/message. | Fewer failed batch renames. |
| Medium | Sidebar exposes 10+ persistent top-level destinations (`AppLayout.vue:108-130`). | Hick’s Law: too many choices slow navigation scanning. | Cognitive load / Progressive disclosure | Collapse less-used sections behind accordions; let users pin favorites. | Faster navigation scanning. |

### B.3 · User Feedback & System Communication

| Severity | Gap | Why It Matters | Principle | Recommended Fix | Expected Impact |
|---|---|---|---|---|---|
| Medium | Vault reveal errors use native `alert()` (`Vault/Index.vue:44-48`). | Breaks app design, blocks UI, poor accessibility. | System feedback / Consistency | Render inline error under the reveal button or use app toast/alert component. | Consistent, non-blocking feedback. |
| Medium | Upload dialog shows only aggregate progress with no per-file status or retry (`UploadModal.vue:119-145`). | Large multi-file uploads fail opaquely. | System feedback / User control | List each file with individual progress, error badge, and retry/remove. | Easier upload troubleshooting. |
| Low-Medium | Loading skeleton is one generic row shape used for tables, grids, and stats (`LoadingSkeleton.vue:5-17`). | Shapes do not match content, increasing perceived loading anxiety. | Cohesion / Emotional impact | Add `variant` prop (`list`, `grid`, `stats`) with matching placeholder shapes. | Reduced layout-shift anxiety. |

### B.4 · Interaction Design

| Severity | Gap | Why It Matters | Principle | Recommended Fix | Expected Impact |
|---|---|---|---|---|---|
| Medium | Drag-to-reorder photos and drag-to-move files have no keyboard alternative (`Photos/Index.vue:366-384`, `Files/Index.vue:161`). | WCAG 2.2 requires a single-pointer alternative for dragging. | Keyboard accessibility / Motor accessibility | Provide “Move up/down” menu items or buttons for reordering; keep menu fallback for folder moves. | Keyboard and motor-impaired users can organize files. |

### B.5 · Responsive Design

| Severity | Gap | Why It Matters | Principle | Recommended Fix | Expected Impact |
|---|---|---|---|---|---|
| Medium | Fixed `100vh` arithmetic overflows on mobile dynamic viewports (`Photos/Index.vue:427,509`, `QuickLookModal.vue:92`, `Notes/Index.vue:223`, `ThreePane.vue:27`). | Browser toolbars on iOS/Android can push controls off-screen. | Responsive layout / Mobile usability | Use `dvh` units where supported, combine with `max-height` and `overflow: auto`. | Layouts adapt to dynamic browser chrome. |

### B.6 · Accessibility (additional specifics)

| Severity | Gap | Why It Matters | Principle | Recommended Fix | Expected Impact |
|---|---|---|---|---|---|
| Medium | PDF preview iframes lack titles (`QuickLookModal.vue:100-104`, `SharedListing.vue:131-136`, `Public/Share.vue:55-59`). | Screen readers cannot identify the purpose of the frame. | WCAG 4.1.2 / 2.4.6 | Add `:title="Preview of ${file.name}"` to every preview iframe. | Assistive technology announces the preview content. |
| Medium | NotesSidebar tree chevrons are nested inside buttons with no `aria-expanded` state (`NotesSidebar.vue:122-127,145-149`). | Screen-reader users cannot tell whether a branch is expanded or collapsed. | WCAG 4.1.2 / 1.3.1 | Make chevron a separate button with `aria-expanded`/`aria-controls`, or expose state on the row button. | Tree state is perceivable and HTML is valid. |

### B.7 · Brand Experience

| Severity | Gap | Why It Matters | Principle | Recommended Fix | Expected Impact |
|---|---|---|---|---|---|
| Critical | Favicon is an empty file (`public/favicon.ico`). | Browser tab shows default blank icon; undermines trust at first glance. | Brand identity / Trust | Generate real `.ico`/PNG/SVG variants and link them in `app.blade.php`. | Immediate brand presence in tabs/bookmarks. |
| Critical | Product name is inconsistent: “Silo” in UI, “File Manager by AJBApps” in README (`AppLayout.vue:88`, `GuestLayout.vue:14`, `README.md:16`). | Users cannot form a stable mental model of the brand. | Brand identity / Cohesion | Pick one name, drive it from config/env everywhere, update README/meta. | Consistent brand memory. |
| Medium | Authenticated footer advertises MIT License / Source on GitHub (`AppLayout.vue:284-294`). | Feels hobbyist for a privacy-first product. | Trust / Professionalism | Move license/source links to About page or login footer only; keep main footer to copyright + version. | More professional impression. |
| Medium | Public share page lacks branded chrome and trust cues (`Public/Share.vue:27-78`). | External recipients see no logo or security context, increasing phishing anxiety. | Trust / Brand identity | Add product logo, lock icon, and permissions/expiry summary before unlock. | Recipients trust the link. |
| Low | Error page uses a muted gray icon instead of brand color (`Errors/Error.vue:31`). | Errors are an opportunity to show polish and personality. | Emotional impact / Brand identity | Use primary color for the icon, add a friendly message, and include a support action. | Errors feel recoverable rather than broken. |

---

## Updated Clarifying Question

Given the additional gaps above, the sequencing question becomes more important: should the design-system pass tackle **brand + accessibility + responsive foundation** first, then **polish and consistency**, or do you want a single comprehensive pass? Say the word and I can convert these gaps into sequenced `todo.md` items without touching code.
