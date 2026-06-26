# `@velkymx/vibeui` — Upstream Issues Backlog

> **Purpose.** This document is the source material for filing issues against the `@velkymx/vibeui` library. Each section is one issue. Copy the body to the upstream tracker (GitHub / GitLab) and link back to the corresponding item in `todo.md`.
>
> **Strategy.** We do **not** own `@velkymx/vibeui` upstream. The repo at `resources/js/Components/` has a thin shim layer (`AppModal`, `AppFormGroup`, `AppButton`, `AppInput`) that **fixes the defects locally** and proxies the rest. The shim exists so v1.0 can ship regardless of upstream landing times. See `todo.md` → **REF-P1-06** for the shim.
>
> **How to file.** One issue per section. Tag with `a11y`, `v2`, `breaking-change-candidate`, and the WCAG criterion it violates. Reference `todo.md#<ID>` in the body so the linkage is bidirectional.

---

## Index

| # | Component | Title | WCAG | Blocks |
|---|---|---|---|---|
| 1 | `VibeModal` | Auto-focus first field on open | 2.4.3 | FE-P0-28 |
| 2 | `VibeModal` | No `inert` on background, no focus trap | 2.1.2 | FE-P2-53 |
| 3 | `VibeModal` | No `Cmd/Ctrl+Enter` submit | 2.1.1 | FE-P2-52 |
| 4 | `VibeFormGroup` | No `id`/`for` linkage | 1.3.1, 4.1.2 | FE-P0-29 |
| 5 | `VibeFormGroup` | No `aria-describedby` for help / error | 1.3.1, 3.3.1 | FE-P0-29 |
| 6 | `VibeFormGroup` | No required / optional indicator | 3.3.2 | FE-P0-30 |
| 7 | `VibeFormGroup` | No `role="alert"` on error region | 4.1.3 | FE-P0-31 |
| 8 | `VibeFormGroup` | No top-of-form error summary helper | 3.3.1 | FE-P0-32 |
| 9 | `VibeButton` | Disabled state contrast < 3:1 | 1.4.3 | FE-P0-33 |
| 10 | `VibeButton` | Icon-only button requires external `aria-label` (no lint or required prop) | 4.1.2 | FE-P1-47 |
| 11 | `VibeFormInput` | Placeholder text inherits browser default < 3:1 | 1.4.3 | FE-P0-34 |
| 12 | `VibeFormInput` | No "show password" toggle | UX gap, a11y adjacent | FE-P1-50 |
| 13 | `VibeFormInput` | No password-strength meter prop | UX gap | FE-P1-52 |
| 14 | `VibeFormInput` | `autocomplete` token list incomplete | 1.3.5 | FE-P1-50 |
| 15 | `VibeFormInput` | `inputmode` not exposed | 2.1.1, mobile UX | FE-P1-50 |

---

## Issue 1 — `VibeModal` does not auto-focus the first field

**Tags:** `a11y`, `enhancement`, `v2`
**WCAG:** 2.4.3 Focus Order
**Blocks:** `todo.md#FE-P0-28`

### Problem

When a `VibeModal` opens, focus stays on the trigger button (or the page underneath). Keyboard and screen-reader users must tab through the entire page to reach the form. This is a WCAG 2.4.3 violation and the highest-frequency a11y defect in our consumer.

### Repro

```vue
<VibeModal v-model="open" title="Add bookmark">
  <VibeFormInput v-model="title" placeholder="Title" />
  <VibeFormInput v-model="url" placeholder="URL" />
</VibeModal>
<button @click="open = true">Open</button>
```

1. Tab to the trigger button.
2. Press Enter to open the modal.
3. Inspect `document.activeElement` — it is still the trigger button, **not** the Title input.

### Expected

Focus moves to the first focusable descendant of the modal within 100 ms of `open` becoming `true`. Repeated opens on the same modal instance (e.g. closing then reopening) must re-focus.

### Actual

Focus is never moved. Tab order continues from the trigger, into the modal, then into the page below the modal (no focus trap, see Issue 2).

### Suggested fix

```ts
watch(() => props.open, async (isOpen) => {
    if (!isOpen) return;
    await nextTick();
    const root = modalRef.value?.$el ?? modalRef.value;
    const first = root?.querySelector<HTMLElement>(
        'input:not([type="hidden"]), select, textarea, button, [tabindex]:not([tabindex="-1"])'
    );
    first?.focus();
});
```

Expose this behavior as a prop `autoFocus` (default `true`) so consumers can opt out for cases where the modal opens on a long-running async transition.

### Acceptance criteria

- [ ] Opening any `VibeModal` moves `document.activeElement` to the first focusable descendant within 100 ms.
- [ ] Re-opening the modal after closing re-focuses (no stale focus).
- [ ] `autoFocus` prop allows consumers to opt out.
- [ ] Vitest unit test asserts focus moves on `open` and resets on `close`.

---

## Issue 2 — `VibeModal` does not apply `inert` to the background and has no focus trap

**Tags:** `a11y`, `breaking-change-candidate`, `v2`
**WCAG:** 2.1.2 No Keyboard Trap, 4.1.2 Name / Role / Value
**Blocks:** `todo.md#FE-P2-53`

### Problem

While a `VibeModal` is open, the page underneath remains in the tab order and the screen-reader tree. `Tab` and `Shift+Tab` can escape the modal into the page below. VoiceOver and NVDA announce both layers. This is a WCAG 2.1.2 misuse (the inverse problem — the modal *should* trap focus, not the page).

### Repro

1. Open any modal (e.g. `<VibeModal v-model="open">`).
2. Press `Tab` repeatedly.
3. After the last focusable element inside the modal, focus jumps to a button on the page behind the modal.

### Expected

- `inert` is applied to all elements outside the modal when it is open.
- `Tab` cycles through focusable elements inside the modal only.
- On close, focus is restored to the element that opened the modal (the trigger).

### Suggested fix

```ts
import { onBeforeUnmount, watch } from 'vue';

const previouslyFocused = ref<HTMLElement | null>(null);

watch(() => props.open, async (isOpen) => {
    if (isOpen) {
        previouslyFocused.value = document.activeElement as HTMLElement;
        await nextTick();
        // Apply inert to all siblings of the modal root
        const root = modalRef.value?.$el?.parentElement;
        Array.from(root?.children ?? []).forEach((el) => {
            if (el === modalRef.value?.$el) return;
            (el as HTMLElement).inert = true;
        });
        // Trap focus (or use @vueuse/integrations/useFocusTrap)
    } else {
        // Restore
        Array.from(root?.children ?? []).forEach((el) => {
            (el as HTMLElement).inert = false;
        });
        previouslyFocused.value?.focus();
    }
});

onBeforeUnmount(() => {
    // Ensure inert is cleared on unmount
});
```

### Acceptance criteria

- [ ] `Tab` cannot leave the open modal.
- [ ] `Shift+Tab` cannot leave the open modal from the top.
- [ ] Screen readers announce only the modal contents (verified with `axe.run` + manual NVDA test).
- [ ] On close, focus returns to the element that opened the modal.
- [ ] Unmounting the modal mid-open does not leave the page `inert`.

---

## Issue 3 — `VibeModal` has no `Cmd/Ctrl+Enter` to submit

**Tags:** `enhancement`, `keyboard`, `v2`
**WCAG:** 2.1.1 Keyboard
**Blocks:** `todo.md#FE-P2-52`

### Problem

Forms inside a `VibeModal` with a `<textarea>` (e.g. the Files/Editor save-version note, Profile bio) require the user to mouse over to the Save button after typing a multi-line value. Apple Mail, Google Docs, and Slack all support `Cmd+Enter` (macOS) / `Ctrl+Enter` (Windows/Linux) to submit the enclosing form.

### Repro

1. Open a modal containing a `<textarea>` (e.g. the version-note modal in `Files/Editor.vue`).
2. Type a multi-line note.
3. Press `Cmd+Enter` (macOS) or `Ctrl+Enter` (Windows/Linux).
4. Nothing happens.

### Expected

`Cmd+Enter` / `Ctrl+Enter` inside any form within the modal submits the form (`form.requestSubmit()`).

### Suggested fix

Add a `keydown` listener at the modal root that intercepts `Cmd/Ctrl+Enter` and dispatches a submit on the first form:

```ts
function onKeydown(e: KeyboardEvent) {
    if (!(e.metaKey || e.ctrlKey) || e.key !== 'Enter') return;
    const form = (e.currentTarget as HTMLElement).querySelector('form');
    form?.requestSubmit();
}
```

Bind via `@keydown="onKeydown"` on the modal root. Make this opt-out via a `submitOnMetaEnter` prop (default `true`).

### Acceptance criteria

- [ ] `Cmd+Enter` / `Ctrl+Enter` in any input within the modal submits the first enclosing `<form>`.
- [ ] Default behaviour (plain `Enter` in a single-line input still submits; `Enter` in a textarea inserts a newline).
- [ ] Prop allows consumers to opt out.
- [ ] Vitest test asserts the form is submitted.

---

## Issue 4 — `VibeFormGroup` does not generate an `id` / `for` linkage between label and input

**Tags:** `a11y`, `breaking-change-candidate`, `v2`
**WCAG:** 1.3.1 Info and Relationships, 4.1.2 Name / Role / Value
**Blocks:** `todo.md#FE-P0-29`

### Problem

```vue
<VibeFormGroup label="Email" :error="form.errors.email">
  <VibeFormInput v-model="form.email" type="email" />
</VibeFormGroup>
```

The rendered DOM has a `<label>` and a nested `<input>`, but no `for` / `id` linkage. Screen readers may or may not associate the label with the input, depending on the user agent. axe-core reports `label-content-name-mismatch` and `form-field-multiple-labels` violations in this pattern.

### Repro

Render the snippet above. Run `axe.run()`. Inspect the rendered HTML — no `for` / `id` attributes.

### Expected

`VibeFormGroup` auto-generates a unique `id` (via `useId()`), passes it to the label via `for`, and the inner input receives the same `id` via either a default slot binding or an explicit `inputId` prop.

### Suggested fix

```vue
<script setup lang="ts">
import { useId, computed } from 'vue';
const props = defineProps<{ label: string; helpText?: string; error?: string | null }>();
const uid = useId();
defineExpose({ uid });
const helpId = computed(() => props.helpText ? `${uid}-help` : null);
const errorId = computed(() => props.error ? `${uid}-error` : null);
</script>
<template>
    <label :for="uid" class="form-label">{{ label }}</label>
    <slot :inputId="uid" :describedBy="[helpId, errorId].filter(Boolean).join(' ') || null" />
    <small v-if="helpText" :id="helpId" class="form-text text-muted">{{ helpText }}</small>
    <small v-if="error" :id="errorId" class="invalid-feedback" role="alert">{{ error }}</small>
</template>
```

Consumers use the scoped slot:

```vue
<VibeFormGroup label="Email" :error="form.errors.email">
    <template #default="{ inputId, describedBy }">
        <VibeFormInput :id="inputId" v-model="form.email" :aria-describedby="describedBy" />
    </template>
</VibeFormGroup>
```

### Acceptance criteria

- [ ] `axe.run()` reports zero `label-content-name-mismatch` violations on every form.
- [ ] Vitest test asserts the label's `for` matches the input's `id`.
- [ ] Multiple `VibeFormGroup` instances on the same page have unique ids.

---

## Issue 5 — `VibeFormGroup` does not expose help / error ids to the input's `aria-describedby`

**Tags:** `a11y`, `enhancement`, `v2`
**WCAG:** 1.3.1, 3.3.1 Error Identification
**Blocks:** `todo.md#FE-P0-29`

### Problem

Help text (e.g. "Leave blank to keep the current password") and error messages rendered by `VibeFormGroup` are **not** announced by screen readers when the user focuses the input, because the input has no `aria-describedby` attribute pointing to them.

### Repro

1. Render `<VibeFormGroup label="Password" help-text="At least 12 characters" :error="form.errors.password">` with `<VibeFormInput v-model="form.password">`.
2. Focus the input with a screen reader.
3. The screen reader reads "Password, edit text." The help text and (if any) error are silent.

### Expected

The input's `aria-describedby` references both the help text element's `id` and the error element's `id` (see Issue 4 for the ID generation).

### Suggested fix

Use the scoped-slot pattern in Issue 4 to surface the `describedBy` value:

```vue
<VibeFormGroup label="Password" help-text="At least 12 characters" :error="form.errors.password">
    <template #default="{ inputId, describedBy }">
        <VibeFormInput :id="inputId" v-model="form.password" :aria-describedby="describedBy" />
    </template>
</VibeFormGroup>
```

### Acceptance criteria

- [ ] `axe.run()` reports zero `aria-input-field-name` or related violations.
- [ ] Vitest test asserts `aria-describedby` contains both ids.
- [ ] When the form has no error, `aria-describedby` references only the help text.

---

## Issue 6 — `VibeFormGroup` has no visible required / optional indicator

**Tags:** `a11y`, `enhancement`, `v2`
**WCAG:** 3.3.2 Labels or Instructions
**Blocks:** `todo.md#FE-P0-30`

### Problem

Every form in our consumer uses `required` on the input but renders no `*` or "(optional)" marker. Sighted users cannot tell which fields are required. WCAG 3.3.2 explicitly recommends a visible indicator.

### Repro

```vue
<VibeFormGroup label="Email">
    <VibeFormInput v-model="form.email" type="email" required />
</VibeFormGroup>
```

The rendered label is just "Email" — no `*`, no "(required)".

### Expected

Convention: a **red `*`** for required, **gray "(optional)"** for non-required. Provide a `required` prop on `VibeFormGroup` (boolean, default `false`). Render:

```vue
<label :for="uid" class="form-label">
    {{ label }}
    <span v-if="required" class="text-danger ms-1" aria-hidden="true">*</span>
    <span v-else class="text-muted ms-1 small" aria-hidden="true">(optional)</span>
</label>
<span class="visually-hidden" v-if="required">required</span>
```

### Acceptance criteria

- [ ] `required: true` renders a red `*` next to the label.
- [ ] `required: false` (default) renders gray "(optional)".
- [ ] Sighted and screen-reader users both receive the signal (visually hidden span for the latter).
- [ ] Vitest snapshot test.

---

## Issue 7 — `VibeFormGroup` does not mark the error message as a live region

**Tags:** `a11y`, `enhancement`, `v2`
**WCAG:** 4.1.3 Status Messages
**Blocks:** `todo.md#FE-P0-31`

### Problem

When `form.errors` is populated after a failed submit, the error message rendered by `VibeFormGroup` is announced to screen readers only if the user happens to re-focus the input. WCAG 4.1.3 (Status Messages) requires that messages about the result of an action be announced without requiring focus.

### Repro

1. Submit a form with bad input.
2. Stay focused on the submit button.
3. Inspect with a screen reader. The new error message is silent.

### Expected

The error region has `role="alert"` (or `aria-live="polite"` with `aria-atomic="true"`). New errors are announced within 200 ms.

### Suggested fix

```vue
<small v-if="error" :id="errorId" class="invalid-feedback d-block" role="alert">
    {{ error }}
</small>
```

### Acceptance criteria

- [ ] New error is announced within 200 ms (Vitest + jsdom + `@testing-library/vue`).
- [ ] Clearing the error (`form.errors = {}`) does not announce (avoids chatter).

---

## Issue 8 — `VibeFormGroup` does not provide a top-of-form error summary

**Tags:** `a11y`, `enhancement`, `v2`
**WCAG:** 3.3.1 Error Identification
**Blocks:** `todo.md#FE-P0-32`

### Problem

WCAG 3.3.1 recommends an **error summary at the top of the form** with anchor links to each invalid field, so users can navigate to every problem in one place. The current `VibeFormGroup` renders per-field errors only. Users with 4+ invalid fields must scroll the page to find every error.

### Repro

Submit a form with 4 errors. Each error appears next to its field; the user has to scroll through the entire form.

### Expected

Provide an `<ErrorSummary>` slot / prop on a top-level wrapper, or a separate `<VibeFormErrorSummary :errors="form.errors" />` component that:
- Renders a `role="alert"` block with a heading ("Please fix the following:").
- Lists each error with a link that focuses the offending field.
- Auto-appears when any error is set.

### Suggested fix

New component `VibeFormErrorSummary.vue`:

```vue
<script setup lang="ts">
const props = defineProps<{ errors: Record<string, string> }>();
const entries = computed(() => Object.entries(props.errors).filter(([, v]) => v));
</script>
<template>
    <div v-if="entries.length" role="alert" aria-live="polite" class="alert alert-danger">
        <strong>Please fix the following:</strong>
        <ul class="mb-0">
            <li v-for="[key, msg] in entries" :key="key">
                <a :href="`#field-${key}`" @click.prevent="$emit('focus', key)">{{ msg }}</a>
            </li>
        </ul>
    </div>
</template>
```

Each `VibeFormGroup` should render its input with `:id="\`field-${name}\`"` to support the anchor.

### Acceptance criteria

- [ ] `<VibeFormErrorSummary>` appears on first error, disappears when all errors clear.
- [ ] Clicking a link focuses the corresponding field.
- [ ] axe-core reports zero `error-summary-missing` violations.
- [ ] Vitest test.

---

## Issue 9 — `VibeButton` disabled label contrast < 3:1

**Tags:** `a11y`, `breaking-change-candidate`, `v2`
**WCAG:** 1.4.3 Contrast (Minimum)
**Blocks:** `todo.md#FE-P0-33`

### Problem

Every `:disabled` `VibeButton` renders with `--bs-btn-color` faded to `--bs-secondary-color` (#6c757d) on `--bs-secondary-bg` (#e9ecef), giving roughly **2.8:1** contrast — below the **4.5:1** AA threshold for normal text and even the **3:1** AA-large threshold. The disabled label is exactly the affordance a low-vision user needs to read ("Saving…/Save").

### Repro

Render `<VibeButton :disabled="true">Save</VibeButton>`. Run a contrast checker on the rendered text. Result: ~2.8:1.

### Expected

Disabled state contrast ≥ 4.5:1 (or ≥ 3:1 for ≥18pt text, but a button label is usually normal size).

### Suggested fix

Override the disabled token at the source:

```scss
.btn:disabled, .btn.disabled {
    color: var(--bs-body-color) !important;          // ~#212529
    background-color: var(--bs-tertiary-bg) !important; // ~#f8f9fa
    border-color: var(--bs-border-color) !important;
    opacity: 1 !important;  // override the 0.65 alpha that drops contrast further
}
```

Resulting contrast: ~13.5:1. Passes AAA.

### Acceptance criteria

- [ ] Disabled button label contrast ≥ 4.5:1 in both light and dark mode.
- [ ] Playwright `toHaveScreenshot` confirms visual continuity.
- [ ] axe-core reports no `color-contrast` violations on disabled buttons.

---

## Issue 10 — `VibeButton` icon-only instances need a required `aria-label` prop (no lint, no required validator)

**Tags:** `a11y`, `enhancement`, `v2`
**WCAG:** 4.1.2 Name / Role / Value
**Blocks:** `todo.md#FE-P1-47`

### Problem

`<VibeButton>` with only an icon and no text needs an external `aria-label`. The component does not warn or lint when this is missing. Our consumer has ~30 such buttons missing `aria-label` (we audit them by hand today).

### Repro

```vue
<VibeButton variant="secondary" size="sm" outline @click="openTags">
    <VibeIcon icon="tags" />
</VibeButton>
```

No `aria-label`, no text, no `aria-labelledby`. Screen reader announces "button" with no purpose.

### Expected

Either (a) require an `aria-label` prop when the slot has no text content, or (b) provide an `eslint-plugin-vue` rule (`vuejs-accessibility/control-has-aria-label` or similar) the project enables in CI.

### Suggested fix (option A — prop-driven):

```ts
const slots = useSlots();
const hasText = computed(() => {
    const def = slots.default?.() ?? [];
    return def.some((v) => typeof v.children === 'string' && v.children.trim().length > 0);
});
const props = defineProps<{ ariaLabel?: string; /* … */ }>();
if (process.env.NODE_ENV !== 'production' && !props.ariaLabel && !hasText.value) {
    console.warn('[VibeButton] Icon-only buttons require an aria-label prop.');
}
```

### Suggested fix (option B — lint rule):

Add `vuejs-accessibility/control-has-aria-label` to the project's ESLint config and run it in CI.

### Acceptance criteria

- [ ] A warning fires in dev for any icon-only `VibeButton` missing `aria-label`.
- [ ] Project's ESLint config has the lint rule enabled.
- [ ] Vitest / ESLint test asserts the warning fires.

---

## Issue 11 — `VibeFormInput` placeholder contrast inherits browser default < 3:1

**Tags:** `a11y`, `enhancement`, `v2`
**WCAG:** 1.4.3 Contrast (Minimum)
**Blocks:** `todo.md#FE-P0-34`

### Problem

Browser-default placeholder text is rendered at ~60% alpha on the input's text color, giving ~`#a1a8b0` on white — roughly **2.6:1** contrast. Sighted low-vision users cannot read the hint. WCAG explicitly notes placeholders are not a substitute for labels but must be readable when used.

### Repro

Render `<VibeFormInput placeholder="https://…">`. Inspect the rendered text. Result: ~2.6:1.

### Expected

Placeholder contrast ≥ 4.5:1 (normal text).

### Suggested fix

```scss
::placeholder { color: #6c757d !important; opacity: 1 !important; } // 4.5:1 on white
::-webkit-input-placeholder { color: #6c757d !important; opacity: 1 !important; }
:-ms-input-placeholder { color: #6c757d !important; opacity: 1 !important; }
```

Or scope the rule to `[data-vibeui] ::placeholder` so it doesn't leak globally.

### Acceptance criteria

- [ ] Placeholder contrast ≥ 4.5:1 on white and on `--bs-body-bg`.
- [ ] Dark mode placeholder contrast ≥ 4.5:1.
- [ ] axe-core reports no `color-contrast` violations on placeholders.

---

## Issue 12 — `VibeFormInput` has no "show password" toggle

**Tags:** `enhancement`, `a11y-adjacent`, `v2`
**Blocks:** `todo.md#FE-P1-50`

### Problem

Every `VibeFormInput type="password"` in our consumer (Login, Register, Reset, Confirm, Profile, Admin/Users/Edit, Vault reveal-confirm, Public/Share) has no show-password toggle. Apple, Google, Microsoft, 1Password — every modern form has this. Mobile users in particular mistype long passwords constantly.

### Repro

```vue
<VibeFormInput v-model="form.password" type="password" />
```

No toggle button. The user cannot see what they're typing.

### Expected

Add a `showPassword` prop (boolean, default `false`) and a `showToggle` prop (boolean, default `false` for backwards compatibility, but recommended `true` for new forms). When both are `true`, render an eye-icon button at the end of the input that toggles `type` between `password` and `text`.

```vue
<script setup>
const show = ref(false);
const effectiveType = computed(() => (props.type === 'password' && props.showPassword && show.value) ? 'text' : props.type);
</script>
<template>
    <div class="input-group">
        <input :type="effectiveType" v-bind="$attrs" />
        <button v-if="props.showToggle && props.type === 'password'"
                type="button"
                class="btn btn-outline-secondary"
                :aria-label="show ? 'Hide password' : 'Show password'"
                :aria-pressed="show"
                @click="show = !show">
            <VibeIcon :icon="show ? 'eye-slash' : 'eye'" />
        </button>
    </div>
</template>
```

### Acceptance criteria

- [ ] Toggle button appears when `showToggle` is true and `type` is `password`.
- [ ] Clicking toggles the input's `type` between `password` and `text`.
- [ ] `aria-label` and `aria-pressed` correctly describe state.
- [ ] Vitest test for the toggle behaviour.

---

## Issue 13 — `VibeFormInput` has no password-strength meter prop

**Tags:** `enhancement`, `v2`
**Blocks:** `todo.md#FE-P1-52`

### Problem

Consumer wants to show a 4-segment strength meter under the password input (Register, Reset Password). The component has no slot or prop for this.

### Repro

```vue
<VibeFormInput v-model="form.password" type="password" required />
```

No strength meter. Users pick weak passwords, get a server 422, and don't know how to fix it.

### Expected

Add a `showPasswordStrength` prop (boolean, default `false`). When true, render a `<small>` element under the input with a 4-segment bar visualised as Bootstrap progress segments. Strength computed from a configurable function (or default `length + diversity` heuristic). The bar updates reactively as the user types.

### Acceptance criteria

- [ ] `showPasswordStrength` renders a 4-segment meter.
- [ ] Strength updates on every input event.
- [ ] `aria-live="polite"` announces "Password strength: Good" when the level changes.
- [ ] Vitest test for the strength computation.

---

## Issue 14 — `VibeFormInput` `autocomplete` token list is incomplete

**Tags:** `enhancement`, `a11y`, `v2`
**WCAG:** 1.3.5 Identify Input Purpose
**Blocks:** `todo.md#FE-P1-50`

### Problem

`autocomplete` accepts a string but the consumer must remember the full token list (`username`, `current-password`, `new-password`, `email`, `name`, `organization`, `street-address`, `postal-code`, etc.). Easy to typo or miss. The component should provide a typed enum or auto-detect based on `type` and `name`.

### Repro

```vue
<VibeFormInput v-model="form.password" type="password" required />
```

No `autocomplete` set. Password managers may auto-fill with the wrong field.

### Expected

Provide an `autocompleteType` prop (typed enum: `'username' | 'current-password' | 'new-password' | 'email' | 'name' | … | 'off'`) that compiles to the correct token. Auto-detect when not set, based on `type` and `name`.

### Acceptance criteria

- [ ] `autocompleteType` is a typed enum (TypeScript `type AutocompleteType = …`).
- [ ] Auto-detect defaults for `type="email"` (`email`), `type="password"` with `name="current_password"` (`current-password`), etc.
- [ ] Consumers can opt out with `autocompleteType="off"`.
- [ ] Vitest test for the auto-detection logic.

---

## Issue 15 — `VibeFormInput` does not expose `inputmode`

**Tags:** `enhancement`, `mobile`, `a11y`, `v2`
**WCAG:** 2.1.1 Keyboard
**Blocks:** `todo.md#FE-P1-50`

### Problem

Mobile keyboards switch layout based on `inputmode` (`numeric`, `decimal`, `email`, `tel`, `url`, `search`, `text`). The component has no `inputmode` prop. A consumer's `<VibeFormInput type="number" v-model="size">` for size filtering on a phone shows the alphabetic keyboard instead of the numeric pad.

### Repro

1. Open a form with `<VibeFormInput type="number" v-model="size">` on a phone.
2. Tap the field.
3. The full alphabetic keyboard appears. Annoying.

### Expected

Pass-through prop with sensible defaults:

- `type="number"` → `inputmode="decimal"`
- `type="email"` → `inputmode="email"`
- `type="tel"` → `inputmode="tel"`
- `type="url"` → `inputmode="url"`
- `type="search"` → `inputmode="search"`
- otherwise → `inputmode="text"`

### Acceptance criteria

- [ ] All consumer forms with `type="number"` get `inputmode="decimal"` automatically.
- [ ] Consumers can override with an explicit `inputmode` prop.
- [ ] Vitest test for the default mapping.

---

## Filing Checklist

Before opening a GitHub issue, copy the body of the relevant section above and add:

- **Title** matching the section heading
- **Labels:** `a11y`, `enhancement`, `v2` (or `breaking-change-candidate` for Issues 1, 2, 4, 9)
- **Milestone:** `v2.0`
- **Body:** the full section, including Repro / Expected / Actual / Suggested fix / Acceptance criteria
- **Cross-link:** add a comment in the corresponding `todo.md` item with the new upstream issue URL
