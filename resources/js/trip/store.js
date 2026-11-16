/* Client-side copy of the trip. Records are kept in Maps keyed by id. */

export const S = {
    trip: null,
    role: 'viewer',
    days: [],
    acts: new Map(),
    items: new Map(),
    ideas: new Map(),
    notes: new Map(),
    serverTime: null,
    /** Record keys ("act:<id>") with local writes not yet confirmed by the server. */
    pending: new Map(),
};

export const UI = {
    view: 'plan',
    dayId: null,
    open: new Set(),
    expandedRegions: new Set(),
};

export const canEdit = () => S.role === 'owner' || S.role === 'editor';

function upsertAll(map, list, key) {
    for (const record of list || []) {
        if (S.pending.has(`${key}:${record.id}`)) {
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
    S.acts = new Map();
    payload.days.forEach((day) => (day.activities || []).forEach((a) => S.acts.set(a.id, a)));
    S.items = new Map(payload.shopping_items.map((i) => [i.id, i]));
    S.ideas = new Map(payload.ideas.map((i) => [i.id, i]));
    S.notes = new Map(payload.my_notes.map((n) => [n.activity_id, n.note]));
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
    upsertAll(S.acts, payload.activities, 'act');
    upsertAll(S.items, payload.shopping_items, 'item');
    upsertAll(S.ideas, payload.ideas, 'idea');
    for (const note of payload.my_notes || []) {
        if (!S.pending.has(`note:${note.activity_id}`)) {
            S.notes.set(note.activity_id, note.note);
        }
    }
    S.serverTime = payload.server_time || S.serverTime;
}

/** Plain object for the offline cache. */
export function snapshot() {
    return {
        trip: S.trip,
        role: S.role,
        days: S.days,
        acts: [...S.acts.values()],
        items: [...S.items.values()],
        ideas: [...S.ideas.values()],
        notes: [...S.notes.entries()],
        serverTime: S.serverTime,
    };
}

export function restore(saved) {
    S.trip = saved.trip;
    S.role = saved.role;
    S.days = saved.days;
    S.acts = new Map(saved.acts.map((a) => [a.id, a]));
    S.items = new Map(saved.items.map((i) => [i.id, i]));
    S.ideas = new Map(saved.ideas.map((i) => [i.id, i]));
    S.notes = new Map(saved.notes);
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
