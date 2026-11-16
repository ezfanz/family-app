/* Ideas: places to visit if there's time, grouped by region. */

import { S, UI, canEdit, ideaList, dayById } from './store.js';
import { $, esc, cityColor, mapUrl, ICON } from './util.js';
import { write } from './writer.js';

const VISIBLE = 8;

let openIdeaForm = () => {};
let planIdea = () => {};
export function setIdeaHandlers(handlers) {
    openIdeaForm = handlers.openIdeaForm;
    planIdea = handlers.planIdea;
}

export function regions() {
    return [...new Set(ideaList().map((i) => i.region))];
}

function rowHtml(idea, editable) {
    const sub = [idea.city, idea.area].filter(Boolean).join(' · ');
    const planned = idea.planned_day_id ? dayById(idea.planned_day_id) : null;
    let middle = '<span></span>';
    if (planned) {
        middle = `<span class="planned tnum">Day ${planned.day_number}</span>`;
    } else if (editable) {
        middle = `<button type="button" class="plan-btn" data-act="plan" aria-label="Plan ${esc(idea.name)} on a day"><span>${ICON.plus}Plan</span></button>`;
    }
    return `<li class="${idea.is_visited ? 'visited' : ''}" data-id="${idea.id}"><div class="idea-row">
        <label class="check"><input type="checkbox" data-act="visit" ${idea.is_visited ? 'checked' : ''} ${editable ? '' : 'disabled'} aria-label="Visited ${esc(idea.name)}"></label>
        <button type="button" class="item-main" data-act="edit-idea"${editable ? '' : ' aria-disabled="true" tabindex="-1"'}>
            <span class="item-name">${esc(idea.name)}</span>
            ${sub ? `<span class="item-meta"><span class="swatch" style="--c:${cityColor(idea.city)}" aria-hidden="true"></span>${esc(sub)}</span>` : ''}
        </button>
        ${middle}
        <a class="pin" href="${esc(mapUrl(idea.name, idea.city || idea.region))}" target="_blank" rel="noopener" aria-label="Open ${esc(idea.name)} in Google Maps">${ICON.pinLg}</a>
    </div></li>`;
}

export function renderIdeas() {
    const editable = canEdit();
    $('addIdea').classList.toggle('hidden', !editable);
    const list = ideaList();
    if (!list.length) {
        $('ideas').innerHTML = `<p class="lede" style="margin-top:28px">No ideas yet.${editable ? ' Add places you might visit if there’s time.' : ''}</p>`;
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
            <ul class="list" style="border-top:0;margin-top:4px">${shown.map((i) => rowHtml(i, editable)).join('')}</ul>
            ${hiddenCount > 0 ? `<button type="button" class="more-btn" data-act="more" data-region="${esc(region)}">Show ${hiddenCount} more</button>` : ''}
            ${expanded && items.length > VISIBLE ? `<button type="button" class="more-btn" data-act="less" data-region="${esc(region)}">Show fewer</button>` : ''}
        </section>`;
    }).join('');
}

export function saveIdea(idea, changes, revert) {
    write({ key: `idea:${idea.id}`, method: 'PATCH', path: `/ideas/${idea.id}`, body: changes, revert });
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
        if (!idea || !canEdit()) return;
        if (act === 'plan') planIdea(idea);
        if (act === 'edit-idea') openIdeaForm(idea.id);
    });

    $('addIdea').addEventListener('click', () => openIdeaForm(null));
}
