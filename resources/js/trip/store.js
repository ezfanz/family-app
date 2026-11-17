/* Client-side copy of the trip. Records are kept in Maps keyed by id. */

export const S = {
    trip: null,
    role: 'viewer',
    days: [],
    /** Members whose shopping I can see (everyone for the owner), with budgets. */
    shoppers: [],
    /** Logged-in user id (from the page). */
    me: null,
    acts: new Map(),
    items: new Map(),
    ideas: new Map(),
    notes: new Map(),
    /** Tax refund receipts I can see: mine, or everyone's for the owner. */
    receipts: new Map(),
    /** Airport checklist items I have ticked. */
    checklist: [],
    /** Rules from config/tax_refund.php. */
    taxRules: { min_pre_tax_yen: 5000, days_to_export: 90 },
    serverTime: null,
    /** Record keys ("act:<id>") with local writes not yet confirmed by the server. */
    pending: new Map(),
};

export const UI = {
    view: 'plan',
    dayId: null,
    open: new Set(),
    expandedRegions: new Set(),
    /** Shopping: 'all' (owner only) or a user id. */
    shopSel: null,
    /** Shopping list filter by who an item is for: 'all', a name key, or '__none__'. */
    forWho: 'all',
    /** Shopping: 'overview', 'list' or 'receipts' (tax refunds). */
    shopTab: 'overview',
    /** Overview: 'all' (owner only) or a user id, and the selected trip day (null = latest with spending). */
    ovWho: 'all',
    ovDay: null,
    /** Receipts filter chip: 'all', 'eligible', 'customs_confirmed', 'refunded', 'not_eligible'. */
    receiptFilter: 'all',
    /** Itinerary: 'all' or a city name; the day strip only shows that city's days. */
    city: 'all',
    /** Shopping search & filter form: applies to the list in view and to "Everyone" results. */
    filter: { q: '', forWho: '', store: '', status: 'all' },
    /** Ideas: id whose ⋯ menu is open, and id armed for delete. */
    ideaMenu: null,
    ideaArmed: null,
};

export const canEdit = () => S.role === 'owner' || S.role === 'editor';
export const isOwner = () => S.role === 'owner';
/** Shopping is personal: you manage your own list; the owner manages everyone's. */
export const canManageShoppingOf = (userId) => isOwner() || Number(userId) === Number(S.me);

/** You first, then everyone else in the order they joined. */
const meFirst = (list) => [...list].sort((a, b) => Number(b.is_me) - Number(a.is_me));

function mergeShoppers(list) {
    if (!list) return;
    S.shoppers = meFirst(list).map((p) => {
        const local = S.shoppers.find((x) => x.user_id === p.user_id);
        return local && S.pending.has(`budget:${p.user_id}`) ? { ...p, budget_rm: local.budget_rm } : p;
    });
}

/** Pending-write key prefixes that protect each record type from being overwritten. */
const GUARDS = { act: ['act', 'status'], item: ['item'], idea: ['idea'], receipt: ['receipt'] };

function upsertAll(map, list, key) {
    for (const record of list || []) {
        if (GUARDS[key].some((prefix) => S.pending.has(`${prefix}:${record.id}`))) {
            continue;
        }
        if (record.deleted_at) {
            map.delete(record.id);
        } else {
            map.set(record.id, record);
        }
    }
}

/** Replace everything with a full payload from GET /trips/{id}. */
export function loadFull(payload) {
    S.trip = payload.trip;
    S.role = payload.trip.my_role || 'viewer';
    S.days = payload.days.map(({ activities, ...day }) => day);
    S.shoppers = meFirst(payload.shoppers || []);
    S.acts = new Map();
    payload.days.forEach((day) => (day.activities || []).forEach((a) => S.acts.set(a.id, a)));
    S.items = new Map(payload.shopping_items.map((i) => [i.id, i]));
    S.ideas = new Map(payload.ideas.map((i) => [i.id, i]));
    S.notes = new Map(payload.my_notes.map((n) => [n.activity_id, n.note]));
    S.receipts = new Map((payload.receipts || []).map((r) => [r.id, r]));
    S.checklist = payload.my_airport_checklist || [];
    S.taxRules = payload.tax_refund || S.taxRules;
    S.serverTime = payload.server_time;
}

/** Merge a delta from GET /trips/{id}/changes. Records with local pending writes are skipped. */
export function mergeChanges(payload) {
    if (payload.trip) {
        const keepLocalSettings = S.pending.has('trip:settings');
        const previous = S.trip;
        S.trip = payload.trip;
        if (keepLocalSettings && previous) {
            S.trip.exchange_rate_rm_per_100_yen = previous.exchange_rate_rm_per_100_yen;
            S.trip.exchange_rate_auto = previous.exchange_rate_auto;
            S.trip.deposit_rm = previous.deposit_rm;
        }
        S.role = payload.trip.my_role || S.role;
    }
    for (const day of payload.days || []) {
        const index = S.days.findIndex((d) => d.id === day.id);
        const { activities, ...clean } = day;
        if (index >= 0) {
            S.days[index] = clean;
        } else {
            S.days.push(clean);
            S.days.sort((a, b) => a.day_number - b.day_number);
        }
    }
    mergeShoppers(payload.shoppers);
    upsertAll(S.acts, payload.activities, 'act');
    upsertAll(S.items, payload.shopping_items, 'item');
    upsertAll(S.ideas, payload.ideas, 'idea');
    upsertAll(S.receipts, payload.receipts, 'receipt');
    for (const note of payload.my_notes || []) {
        if (!S.pending.has(`note:${note.activity_id}`)) {
            S.notes.set(note.activity_id, note.note);
        }
    }
    // Settings can change on the server (e.g. receipt reading switched on).
    S.taxRules = payload.tax_refund || S.taxRules;
    S.serverTime = payload.server_time || S.serverTime;
}

/** Plain object for the offline cache. */
export function snapshot() {
    return {
        trip: S.trip,
        role: S.role,
        days: S.days,
        shoppers: S.shoppers,
        acts: [...S.acts.values()],
        items: [...S.items.values()],
        ideas: [...S.ideas.values()],
        notes: [...S.notes.entries()],
        receipts: [...S.receipts.values()],
        checklist: S.checklist,
        taxRules: S.taxRules,
        serverTime: S.serverTime,
    };
}

export function restore(saved) {
    S.trip = saved.trip;
    S.role = saved.role;
    S.days = saved.days;
    S.shoppers = saved.shoppers || [];
    S.acts = new Map(saved.acts.map((a) => [a.id, a]));
    S.items = new Map(saved.items.map((i) => [i.id, i]));
    S.ideas = new Map(saved.ideas.map((i) => [i.id, i]));
    S.notes = new Map(saved.notes);
    S.receipts = new Map((saved.receipts || []).map((r) => [r.id, r]));
    S.checklist = saved.checklist || [];
    S.taxRules = saved.taxRules || S.taxRules;
    S.serverTime = saved.serverTime;
}

export const dayById = (id) => S.days.find((d) => d.id === id);

export function dayActs(dayId) {
    return [...S.acts.values()]
        .filter((a) => a.day_id === dayId && !a.deleted_at)
        .sort((x, y) => x.time.localeCompare(y.time) || (x.sort_order ?? 0) - (y.sort_order ?? 0));
}

export function allActs() {
    return [...S.acts.values()].filter((a) => !a.deleted_at && dayById(a.day_id));
}

export function shoppingList() {
    return [...S.items.values()]
        .filter((i) => !i.deleted_at)
        .sort((a, b) => (a.sort_order ?? 0) - (b.sort_order ?? 0));
}

export function ideaList() {
    return [...S.ideas.values()]
        .filter((i) => !i.deleted_at)
        .sort((a, b) => (a.sort_order ?? 0) - (b.sort_order ?? 0));
}
