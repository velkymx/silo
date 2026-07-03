import { config } from '@vue/test-utils';
import { h } from 'vue';

// Stand-ins for globally-registered VibeUI components + app layouts. Interactive
// ones (button/inputs/modal) render real DOM and forward events so component
// handlers fire and register in coverage; the rest forward their default slot.
// eslint-disable-next-line @typescript-eslint/no-explicit-any
type Slots = Record<string, ((p?: unknown) => any) | undefined>;

const passthrough = (name: string, scope: Record<string, unknown> = {}) => ({
    name,
    inheritAttrs: false,
    setup(_: unknown, { slots }: { slots: Slots }) {
        return () => h('div', { 'data-stub': name }, (slots.default ? [slots.default(scope)] : []) as never);
    },
});

// <input>-like model stub.
const inputStub = (name: string, type = 'text') => ({
    name,
    props: ['modelValue'],
    emits: ['update:modelValue'],
    template: `<input data-stub="${name}" type="${type}" :value="modelValue" @input="$emit('update:modelValue', $event.target.value)" />`,
});

// eslint-disable-next-line @typescript-eslint/no-explicit-any
const components: Record<string, any> = {
    VibeIcon: { name: 'VibeIcon', template: '<i class="bi"></i>' },
    VibeSpinner: { name: 'VibeSpinner', template: '<span class="spinner"></span>' },

    // Forwards fallthrough @click to a real button.
    VibeButton: { name: 'VibeButton', template: '<button class="vibe-btn"><slot /></button>' },
    VibeButtonGroup: passthrough('VibeButtonGroup'),

    VibeFormInput: inputStub('VibeFormInput'),
    VibeFormTextarea: inputStub('VibeFormTextarea'),
    VibeFormSelect: inputStub('VibeFormSelect'),
    VibeFileInput: inputStub('VibeFileInput', 'file'),
    VibeFormCheckbox: {
        name: 'VibeFormCheckbox',
        props: ['modelValue'],
        emits: ['update:modelValue'],
        template: '<input type="checkbox" :checked="modelValue" @change="$emit(\'update:modelValue\', $event.target.checked)" />',
    },
    VibeFormSwitch: {
        name: 'VibeFormSwitch',
        props: ['modelValue'],
        emits: ['update:modelValue'],
        template: '<input type="checkbox" role="switch" :checked="modelValue" @change="$emit(\'update:modelValue\', $event.target.checked)" />',
    },
    VibeFormGroup: passthrough('VibeFormGroup'),
    // Render prepend + default + append so input-group affixes (buttons, dropdowns) mount.
    VibeInputGroup: {
        name: 'VibeInputGroup',
        inheritAttrs: false,
        setup(_: unknown, { slots }: { slots: Slots }) {
            return () => h('div', { 'data-stub': 'VibeInputGroup' }, [
                slots.prepend ? slots.prepend() : null,
                slots.default ? slots.default() : null,
                slots.append ? slots.append() : null,
            ]);
        },
    },
    VibeAutocomplete: inputStub('VibeAutocomplete'),
    VibeFormWysiwyg: inputStub('VibeFormWysiwyg'),

    // Modal: render header/default/footer slots (so their handlers wire up).
    VibeModal: {
        name: 'VibeModal',
        props: ['modelValue'],
        setup(_: unknown, { slots }: { slots: Slots }) {
            return () => h('div', { 'data-stub': 'VibeModal' }, [
                slots.header ? slots.header() : null,
                slots.default ? slots.default() : null,
                slots.footer ? slots.footer() : null,
            ]);
        },
    },
    // DataTable: render the default slot + every #cell(*) / #empty slot once per
    // item so cell templates (and their handlers) execute.
    VibeDataTable: {
        name: 'VibeDataTable',
        props: ['items', 'columns', 'searchable'],
        setup(props: { items?: unknown[]; columns?: { key: string }[] }, { slots }: { slots: Slots }) {
            return () => h('div', { 'data-stub': 'VibeDataTable' },
                (props.items ?? []).map((item) =>
                    (props.columns ?? []).map((col) => {
                        const slot = slots[`cell(${col.key})`];
                        return slot ? slot({ item }) : null;
                    }),
                ),
            );
        },
    },

    VibeDropdown: {
        name: 'VibeDropdown',
        props: ['items'],
        emits: ['item-click'],
        setup(props: { items?: unknown[] }, { slots, emit }: { slots: Slots; emit: (e: string, p: unknown) => void }) {
            return () => h('div', { 'data-stub': 'VibeDropdown' }, [
                slots.button ? slots.button() : null,
                slots.default ? slots.default() : null,
                (props.items ?? []).map((item) =>
                    h('button', { class: 'dd-item', onClick: () => emit('item-click', { item }) },
                        slots.item ? [slots.item({ item })] : []),
                ),
            ]);
        },
    },

    VibeCard: passthrough('VibeCard'),
    VibeContainer: passthrough('VibeContainer'),
    // Row/Col forward fallthrough attrs (class, data-pane, …) like the real
    // components do — pane layout tests target those attributes.
    VibeRow: {
        name: 'VibeRow',
        props: ['tag', 'gutters', 'rowCols', 'rowColsSm', 'rowColsMd', 'rowColsLg', 'rowColsXl', 'rowColsXxl', 'alignItems', 'justifyContent'],
        template: '<div class="row" data-stub="VibeRow"><slot /></div>',
    },
    VibeCol: {
        name: 'VibeCol',
        props: ['tag', 'cols', 'sm', 'md', 'lg', 'xl', 'xxl', 'offset', 'order', 'alignSelf'],
        template: '<div class="col" data-stub="VibeCol"><slot /></div>',
    },
    VibeAlert: passthrough('VibeAlert'),
    VibeBadge: passthrough('VibeBadge'),
    VibeProgress: passthrough('VibeProgress'),
    VibeBreadcrumb: passthrough('VibeBreadcrumb'),
    VibeNav: passthrough('VibeNav'),
    VibeOffcanvas: passthrough('VibeOffcanvas'),
    VibeCarousel: passthrough('VibeCarousel'),
    VibeTabs: passthrough('VibeTabs'),
    VibeTab: passthrough('VibeTab'),
    VibeDroppable: passthrough('VibeDroppable', { isOver: false }),
    VibeDraggable: passthrough('VibeDraggable', { isDragging: false }),

    // Sortable list: render the #default slot once per item so item templates run.
    VibeSortable: {
        name: 'VibeSortable',
        props: ['modelValue', 'itemKey'],
        setup(props: { modelValue?: unknown[] }, { slots }: { slots: Slots }) {
            return () => h('div', { 'data-stub': 'VibeSortable' },
                (props.modelValue ?? []).map((item) => (slots.default ? slots.default({ item }) : null)),
            );
        },
    },

    // Accordion: render #title + #content slots for every item and emit
    // item-click when a header is clicked (the stub never collapses —
    // expand/collapse is Bootstrap's job and not unit-tested).
    VibeAccordion: {
        name: 'VibeAccordion',
        props: ['items', 'alwaysOpen', 'flush'],
        emits: ['item-click'],
        setup(props: { items?: { id: string }[] }, { slots, emit }: { slots: Slots; emit: (e: string, p: unknown) => void }) {
            return () => h('div', { 'data-stub': 'VibeAccordion' },
                (props.items ?? []).map((item, index) =>
                    h('div', { 'data-acc-item': item.id }, [
                        h('div', { class: 'acc-header', onClick: () => emit('item-click', { item, index }) },
                            slots.title ? [slots.title({ item, index })] : []),
                        slots.content ? slots.content({ item, index }) : null,
                    ]),
                ),
            );
        },
    },
};

config.global.components = components;

// VibeUI registers v-vibe-tooltip globally; stub it as a no-op in tests.
config.global.directives = { 'vibe-tooltip': {} };

// Locally-imported components (layouts) must be stubbed, not globally
// registered, to override the import.
config.global.stubs = {
    UserAvatar: { name: 'UserAvatar', template: '<span class="user-avatar-stub" />', props: ['user', 'size'] },
    AppLayout: {
        name: 'AppLayout',
        setup(_: unknown, { slots }: { slots: Slots }) {
            return () => h('div', { 'data-stub': 'AppLayout' }, [
                slots.sidebar ? slots.sidebar() : null,
                slots['sidebar-bottom'] ? slots['sidebar-bottom']() : null,
                slots.topbar ? slots.topbar() : null,
                slots.default ? slots.default() : null,
            ]);
        },
    },
    GuestLayout: passthrough('GuestLayout'),
};

if (!window.matchMedia) {
    window.matchMedia = ((query: string) => ({
        matches: false,
        media: query,
        onchange: null,
        addEventListener() {},
        removeEventListener() {},
        addListener() {},
        removeListener() {},
        dispatchEvent() {
            return false;
        },
    })) as unknown as typeof window.matchMedia;
}

if (!Element.prototype.scrollIntoView) {
    Element.prototype.scrollIntoView = function scrollIntoView() {};
}

if (!globalThis.ResizeObserver) {
    globalThis.ResizeObserver = class {
        observe() {}
        unobserve() {}
        disconnect() {}
    } as unknown as typeof ResizeObserver;
}
