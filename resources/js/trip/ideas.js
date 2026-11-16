/*
 * Ideas, shared with everyone in the trip (viewers too): add, tick, edit and
 * delete. Each row has a ⋯ menu with Open in Maps and Delete (tap twice),
 * and a deleted idea can be brought back with Undo.
 */

import { S, UI, canEdit, ideaList, dayById } from './store.js';
import { $, esc, cityColor, mapUrl, ICON, toast } from './util.js';
import { write } from './writer.js';
import { api } from './api.js';

const VISIBLE = 8;

let openIdeaForm = () => {};
let planIdea = () => {};
let rerender = () => {};
export function setIdeaHandlers(handlers) {
    openIdeaForm = handlers.openIdeaForm;
    planIdea = handlers.planIdea;
    if (handlers.rerender) rerender = handlers.rerender;
}

export function regions() {
    return [...new Set(ideaList().map((i) => i.region))];
}

const DOTS = '<svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true" style="fill:currentColor"><circle cx="5" cy="12" r="1.8"/><circle cx="12" cy="12" r="1.8"/><circle cx="19" cy="12" r="1.8"/></svg>';
const BIN = '<svg class="i" width="18" height="18" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M9 7V4h6v3M6 7l1 13h10l1-13"/></svg>';

function rowHtml(idea) {
    const sub = [idea.city, idea.area].filter(Boolean).join(' · ') || idea.region;
    const planned = idea.planned_day_id ? dayById(idea.planned_day_id) : null;
    const open = UI.ideaMenu === idea.id;
    const armed = UI.ideaArmed === idea.id;

    let middle = '<span></span>';
    if (planned) {
        middle = `<span class="planned tnum">Day ${planned.day_number}</span>`;
    } else if (!idea.is_visited && canEdit()) {
        middle = `<button type="button" class="plan-btn" data-act="plan" aria-label="Plan ${esc(idea.name)} on a day"><span>${ICON.plus}Plan</span></button>`;
    }

    const menu = open ? `<div class="idea-menu rise">
            <a class="menu-pill" href="${esc(mapUrl(idea.name, idea.city || idea.region))}" target="_blank" rel="noopener">${ICON.pin}Open in Maps</a>
            ${armed
                ? `<button type="button" class="menu-pill danger-solid" data-act="delete-confirm">${BIN}Tap again to delete</button>
                   <button type="button" class="menu-link" data-act="delete-cancel">Cancel</button>`
                : `<button type="button" class="menu-pill danger" data-act="delete">${BIN}Delete</button>`}
        </div>` : '';

    return `<li class="${idea.is_visited ? 'visited' : ''}" data-id="${idea.id}"><div class="idea-row">
        <label class="check"><input type="checkbox" data-act="visit" ${idea.is_visited ? 'checked' : ''} aria-label="Visited ${esc(idea.name)}"></label>
        <button type="button" class="item-main" data-act="edit-idea">
            <span class="item-name">${esc(idea.name)}</span>
            <span class="item-meta"><span class="swatch" style="--c:${cityColor(idea.city)}" aria-hidden="true"></span><span class="ellipsis">${esc(sub)}</span></span>
        </button>
        ${middle}
        <button type="button" class="more-dots${open ? ' on' : ''}" data-act="menu" aria-expanded="${open}" aria-label="More for ${esc(idea.name)}">${DOTS}</button>
        ${menu}
    </div></li>`;
}

export function renderIdeas() {
    const list = ideaList();
    if (!list.length) {
        $('ideas').innerHTML = '<p class="lede" style="margin-top:28px">No ideas yet. Add places you might visit if there’s time.</p>';
        return;
    }
    $('ideas').innerHTML = regions().map((region, index) => {
        const items = list.filter((i) => i.region === region);
        const visited = items.filter((i) => i.is_visited).length;
        const pct = Math.round((visited / items.length) * 100);
        const expanded = UI.expandedRegions.has(region);
        const shown = expanded ? items : items.slice(0, VISIBLE);
        const hiddenCount = items.length - shown.length;
        const headingId = `g-${index}`;
        return `<section class="idea-group" aria-labelledby="${headingId}">
            <div class="row-between"><h2 id="${headingId}">${esc(region)}</h2><span class="muted tnum">${visited} of ${items.length} visited</span></div>
            <div class="bar" aria-hidden="true"><div style="width:${pct}%"></div></div>
            <ul class="list" style="border-top:0;margin-top:4px">${shown.map(rowHtml).join('')}</ul>
            ${hiddenCount > 0 ? `<button type="button" class="more-btn" data-act="more" data-region="${esc(region)}">Show ${hiddenCount} more</button>` : ''}
            ${expanded && items.length > VISIBLE ? `<button type="button" class="more-btn" data-act="less" data-region="${esc(region)}">Show fewer</button>` : ''}
        </section>`;
    }).join('');
}

export function saveIdea(idea, changes, revert) {
    write({ key: `idea:${idea.id}`, method: 'PATCH', path: `/ideas/${idea.id}`, body: changes, revert });
}

/** Delete now, offer Undo for a few seconds (restores on the server too). */
export function deleteIdeaWithUndo(idea) {
    S.ideas.delete(idea.id);
    UI.ideaMenu = null;
    UI.ideaArmed = null;
    rerender();
    write({
        key: `idea:${idea.id}`, method: 'DELETE', path: `/ideas/${idea.id}`,
        revert: () => { S.ideas.set(idea.id, idea); rerender(); },
    });
    toast(`Deleted “${idea.name}”.`, {
        action: 'Undo',
        onAction: async () => {
            S.ideas.set(idea.id, { ...idea, deleted_at: null });
            rerender();
            try {
                const res = await api('PATCH', `/ideas/${idea.id}/restore`);
                if (res?.data) S.ideas.set(res.data.id, res.data);
            } catch {
                toast('Couldn’t bring it back. Try again when you’re online.');
                S.ideas.delete(idea.id);
                rerender();
            }
        },
    });
}

export function bindIdeas() {
    $('ideas').addEventListener('change', (e) => {
        if (e.target.dataset.act !== 'visit') return;
        const idea = S.ideas.get(Number(e.target.closest('li').dataset.id));
        if (!idea) return;
        const before = idea.is_visited;
        idea.is_visited = e.target.checked;
        renderIdeas();
        saveIdea(idea, { is_visited: idea.is_visited }, () => { idea.is_visited = before; renderIdeas(); });
    });

    $('ideas').addEventListener('click', (e) => {
        const el = e.target.closest('[data-act]');
        if (!el) return;
        const act = el.dataset.act;
        if (act === 'more' || act === 'less') {
            act === 'more' ? UI.expandedRegions.add(el.dataset.region) : UI.expandedRegions.delete(el.dataset.region);
            renderIdeas();
            return;
        }
        const idea = S.ideas.get(Number(el.closest('li')?.dataset.id));
        if (!idea) return;
        if (act === 'menu') {
            UI.ideaMenu = UI.ideaMenu === idea.id ? null : idea.id;
            UI.ideaArmed = null;
            renderIdeas();
        } else if (act === 'delete') {
            UI.ideaArmed = idea.id;
            renderIdeas();
        } else if (act === 'delete-cancel') {
            UI.ideaArmed = null;
            renderIdeas();
        } else if (act === 'delete-confirm') {
            deleteIdeaWithUndo(idea);
        } else if (act === 'plan' && canEdit()) {
            planIdea(idea);
        } else if (act === 'edit-idea') {
            openIdeaForm(idea.id);
        }
    });

    $('addIdea').addEventListener('click', () => openIdeaForm(null));
}
