/*
 * Shopping → Overview: spending dashboard for one person or the group.
 * Owners pick Everyone or anyone; others only ever see their own (the trip
 * download only contains their data, and the API returns 403 otherwise).
 */

import { S, UI, isOwner } from './store.js';
import { $, esc, yen, rm, fmtDate, weekday } from './util.js';
import { rate, avatar, displayName } from './shopping.js';
import { overviewFor } from './overviewcalc.js';

const CHART_HEIGHT = 140;
const WARN = '<svg class="i" width="14" height="14" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3l9.5 17h-19z"/><path d="M12 10v4M12 17h.01"/></svg>';
const JAPAN_DAY = new Intl.DateTimeFormat('en-CA', { timeZone: 'Asia/Tokyo', year: 'numeric', month: '2-digit', day: '2-digit' });

let onGo = () => {};

const shortDate = (iso) => `${weekday(iso, true)} ${fmtDate(iso)}`;
const personById = (id) => S.shoppers.find((p) => Number(p.user_id) === Number(id));

function data() {
    return {
        members: S.shoppers.map((p) => ({ user_id: p.user_id, name: p.name, budget_rm: Number(p.budget_rm) || 0 })),
        items: [...S.items.values()],
        receipts: [...S.receipts.values()],
        rate: rate(),
        startDate: S.trip.start_date,
        endDate: S.trip.end_date,
        today: JAPAN_DAY.format(new Date()),
        minimum: Number(S.taxRules?.min_pre_tax_yen) || 5000,
    };
}

/** Whose overview is showing: Everyone (null) only for the owner; everyone else sees themselves. */
function who() {
    if (!isOwner()) return S.me;
    if (UI.ovWho == null || (UI.ovWho !== 'all' && !personById(UI.ovWho))) UI.ovWho = 'all';
    return UI.ovWho === 'all' ? null : UI.ovWho;
}

/** Start from the default each time Shopping opens: owner → Everyone, others → themselves. */
export function resetOverview() {
    UI.ovWho = null; // resolved in who(), once the role is known
    UI.ovDay = null;
}

function pick(userId) {
    UI.ovWho = userId;
    UI.ovDay = null; // jump to that person's latest day with spending
    renderOverview();
}

/** Whose overview the owner is looking at: 'all' or a user id (picked in the Filter drawer). */
export function overviewWho() {
    const userId = who();
    return userId === null ? 'all' : userId;
}

/** Show someone's overview (or 'all'), from the Filter drawer's Apply. */
export function showOverviewOf(userId) {
    pick(userId);
}

function renderHero(view, person) {
    $('ovHeroLabel').textContent = person ? `${person.is_me ? 'You' : person.name} spent` : 'Group spent';
    $('ovSpent').textContent = rm(view.spent_rm);
    $('ovBudgetLine').textContent = `of ${rm(view.budget_rm)} budget · ${yen(view.spent_yen)}`;
    const meter = $('ovMeter');
    meter.setAttribute('aria-valuenow', String(view.used_pct));
    meter.classList.toggle('over', view.over_budget);
    $('ovMeterBar').style.width = `${Math.min(100, view.used_pct)}%`;
    $('ovUsed').textContent = `${view.used_pct}% used`;
    const left = $('ovLeft');
    left.className = view.over_budget ? 'over' : 'ok';
    left.innerHTML = view.over_budget ? `${WARN}Over by ${rm(-view.left_rm)}` : `${rm(view.left_rm)} left`;
}

function renderTiles(view) {
    $('ovToBuy').textContent = String(view.items_to_buy);
    $('ovBoughtLine').textContent = `${view.items_bought} of ${view.items_total} bought`;
    $('ovItemsBar').style.width = `${view.items_total ? (view.items_bought / view.items_total) * 100 : 0}%`;
    $('ovReceipts').textContent = String(view.receipts);
    $('ovEligibleLine').textContent = `${view.receipts_eligible} eligible for refund`;
    $('ovReceiptsBar').style.width = `${view.receipts ? (view.receipts_eligible / view.receipts) * 100 : 0}%`;
    $('ovRefundYen').textContent = yen(view.refund_yen);
    $('ovRefundRm').textContent = `≈ ${rm(view.refund_rm)}`;
    const last = S.days[S.days.length - 1];
    $('ovRefundLine').textContent = view.refund_yen
        ? `Claim at customs on ${last ? shortDate(last.date) : 'your last day'}`
        : 'No eligible receipts yet';
}

function renderDays(view, unit) {
    $('ovUnitDay').textContent = unit;
    if (UI.ovDay == null || !view.days.some((d) => d.n === UI.ovDay)) UI.ovDay = view.selected_day;
    const max = view.axis_max;
    const current = view.days.find((d) => d.n === UI.ovDay) || view.days[0];

    $('ovSelValue').textContent = current.future ? 'Not yet' : rm(current.rm);
    $('ovSelLine').textContent = `Day ${current.n} · ${shortDate(current.date)} · ${current.future ? 'coming up' : `${current.items} item${current.items === 1 ? '' : 's'}`}`;

    $('ovGrid').innerHTML = [0, max / 2, max].map((value) => {
        const bottom = (value / max) * CHART_HEIGHT;
        return `<div class="ov-line${value === 0 ? ' base' : ''}" style="bottom:${bottom}px"></div><span class="ov-tick tnum" style="bottom:${bottom + 2}px">${value.toLocaleString('en-US')}</span>`;
    }).join('');

    const columns = `repeat(${view.days.length}, minmax(0, 1fr))`;
    $('ovCols').style.gridTemplateColumns = columns;
    $('ovNums').style.gridTemplateColumns = columns;
    $('ovCols').innerHTML = view.days.map((d) => {
        const on = d.n === current.n;
        const height = d.future ? 18 : (d.rm > 0 ? Math.max(4, (d.rm / max) * CHART_HEIGHT) : 2);
        const kind = d.future ? 'ahead' : (d.rm > 0 ? 'spent' : 'none');
        const label = `Day ${d.n}, ${shortDate(d.date)}: ${d.future ? 'coming up' : rm(d.rm)}`;
        return `<button type="button" class="ov-col" data-ov-day="${d.n}" aria-pressed="${on}" aria-label="${esc(label)}"><span class="ov-colbar ${kind}${on ? ' on' : ''}" style="height:${height}px"></span></button>`;
    }).join('');
    $('ovNums').innerHTML = view.days.map((d) => `<span class="tnum${d.n === current.n ? ' on' : ''}">${d.n}</span>`).join('');

    const before = $('ovBefore');
    before.classList.toggle('hidden', !(view.before_trip_rm > 0));
    before.textContent = `Before the trip: ${rm(view.before_trip_rm)}`;
}

function renderCategories(view, unit) {
    $('ovUnitCat').textContent = unit;
    const top = view.categories[0]?.rm || 1;
    $('ovCats').innerHTML = view.categories.map((c) => `<li>
        <span class="ov-cat-name">${esc(c.label)}</span>
        <span class="ov-cat-bar" aria-hidden="true"><span style="width:${Math.max(2, (c.rm / top) * 100).toFixed(1)}%"></span></span>
        <span class="ov-cat-value tnum">${rm(c.rm)}</span>
    </li>`).join('');
    $('ovNoCats').classList.toggle('hidden', view.categories.length > 0);
}

function renderPeople(group) {
    const show = isOwner() && S.shoppers.length > 1;
    $('ovPeopleSec').classList.toggle('hidden', !show);
    if (!show) return;
    $('ovPeople').innerHTML = group.people.map((row) => {
        const person = personById(row.user_id);
        const on = Number(UI.ovWho) === Number(row.user_id);
        const faded = UI.ovWho !== 'all' && !on;
        const used = `${row.used_pct}% used${row.over_budget ? '' : ` · ${rm(row.left_rm)} left`}`;
        return `<li><button type="button" class="ov-person${faded ? ' faded' : ''}" data-ov-person="${row.user_id}" aria-pressed="${on}">
            ${avatar(person, 'ov-ava')}
            <span class="ov-person-main">
                <span class="ov-person-top tnum"><span class="ov-person-name">${esc(displayName(person))}</span><span class="ov-person-spent">${rm(row.spent_rm)} <span>of ${rm(row.budget_rm)}</span></span></span>
                <span class="ov-bar big${row.over_budget ? ' over' : ''}" aria-hidden="true"><span style="width:${Math.min(100, row.used_pct)}%"></span></span>
                <span class="ov-person-used tnum">${used}${row.over_budget ? `<span class="ov-over">${WARN}Over by ${rm(-row.left_rm)}</span>` : ''}</span>
            </span>
        </button></li>`;
    }).join('');
}

export function renderOverview() {
    if (!S.trip) return;
    const input = data();
    const userId = who();
    const view = overviewFor(input, userId);
    const person = userId === null ? null : personById(userId);
    const name = person ? displayName(person) : null;

    const todayIso = input.today;
    const todayDay = S.days.find((d) => d.date === todayIso);
    $('shopLede').textContent = `${name || 'Group'} overview · as of ${shortDate(todayIso)}${todayDay ? `, Day ${todayDay.day_number}` : (todayIso < S.trip.start_date ? ', before the trip' : '')}`;

    const unit = person ? `RM, ${person.is_me ? 'you' : person.name}` : 'RM, group';
    renderHero(view, person);
    renderTiles(view);
    renderDays(view, unit);
    renderCategories(view, unit);
    renderPeople(userId === null ? view : overviewFor(input, null));
}

/**
 * @param {{ onGo: (tab: 'list'|'receipts', userId: number|null) => void }} hooks
 */
export function bindOverview(hooks) {
    onGo = hooks.onGo;
    $('overviewPane').addEventListener('click', (e) => {
        const person = e.target.closest('[data-ov-person]');
        if (person) {
            const id = Number(person.dataset.ovPerson);
            pick(Number(UI.ovWho) === id ? 'all' : id);
            return;
        }
        const col = e.target.closest('[data-ov-day]');
        if (col) {
            UI.ovDay = Number(col.dataset.ovDay);
            renderOverview();
            return;
        }
        const go = e.target.closest('[data-ov-go]');
        if (go) onGo(go.dataset.ovGo, who());
    });
}
