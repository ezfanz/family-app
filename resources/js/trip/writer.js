/* Sends local changes to the API after the UI has already been updated (optimistic UI). */

import { api } from './api.js';
import { S } from './store.js';
import { toast } from './util.js';

const hooks = {
    /** True when earlier writes are still queued, so this one must wait its turn. */
    shouldQueue: () => false,
    enqueue: () => {},
    /** Replaced by the offline queue: return true when the write was queued for retry. */
    onRetryable: () => false,
    onChange: () => {},
    /** Number of requests currently on their way to the server. */
    onBusy: () => {},
};

let inFlight = 0;

export function setWriterHooks(next) {
    Object.assign(hooks, next);
}

export function markPending(key) {
    S.pending.set(key, (S.pending.get(key) || 0) + 1);
}

export function clearPending(key) {
    const left = (S.pending.get(key) || 1) - 1;
    if (left <= 0) {
        S.pending.delete(key);
    } else {
        S.pending.set(key, left);
    }
}

/**
 * @param {{key: string, method: string, path: string, body?: object, revert?: () => void, onSaved?: (data: any) => void}} op
 */
export async function write(op) {
    if (hooks.shouldQueue()) {
        hooks.enqueue(op);
        hooks.onChange();
        return null;
    }
    markPending(op.key);
    hooks.onChange();
    hooks.onBusy(++inFlight);
    try {
        const data = await api(op.method, op.path, op.body);
        op.onSaved?.(data);
        return data;
    } catch (error) {
        if (error.retryable && hooks.onRetryable(op)) {
            return null;
        }
        if (op.method === 'DELETE' && error.status === 404) {
            return null;
        }
        op.revert?.();
        if (error.status === 403) {
            toast('You can view this trip but not edit it.');
        } else if (error.status === 422) {
            toast(error.firstError || 'Check the details and try again.');
        } else {
            toast('Couldn’t save just now. Try again in a moment.');
        }
        return null;
    } finally {
        clearPending(op.key);
        hooks.onChange();
        hooks.onBusy(--inFlight);
    }
}
