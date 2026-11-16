/*
 * Snap receipt: the phone's back camera in the browser (getUserMedia), with the
 * system camera / photo picker as a fallback. Long receipts can be taken in two
 * parts. Photos are shrunk here and handed over as JPEG blobs.
 */

import { $, toast } from './util.js';
import { preparePhoto } from './photo-queue.js';

let stream = null;
let parts = [];
let onDone = () => {};

const dlg = () => $('camDlg');
const twoParts = () => $('camTwo').getAttribute('aria-pressed') === 'true';

function status(text, tone = '') {
    const chip = $('camStatus');
    chip.textContent = text;
    chip.className = `cam-status${tone ? ` ${tone}` : ''}`;
    chip.hidden = !text;
}

function stopStream() {
    stream?.getTracks().forEach((track) => track.stop());
    stream = null;
    $('camVideo').srcObject = null;
}

async function startStream() {
    $('camFlash').hidden = true;
    if (!navigator.mediaDevices?.getUserMedia) {
        showFallback('This browser can’t open the camera here. Use the button below.');
        return;
    }
    try {
        stream = await navigator.mediaDevices.getUserMedia({
            audio: false,
            video: { facingMode: { ideal: 'environment' }, width: { ideal: 1920 }, height: { ideal: 1440 } },
        });
    } catch (error) {
        showFallback(error?.name === 'NotAllowedError'
            ? 'Camera permission is off. Allow it in your browser settings, or use the button below.'
            : 'No camera found. Use the button below.');
        return;
    }
    if (!dlg().open) {
        stopStream();
        return;
    }
    const video = $('camVideo');
    video.srcObject = stream;
    await video.play().catch(() => {});
    $('camView').classList.remove('no-camera');
    const track = stream.getVideoTracks()[0];
    $('camFlash').hidden = !track?.getCapabilities?.().torch;
}

function showFallback(message) {
    $('camView').classList.add('no-camera');
    $('camFallbackText').textContent = message;
}

async function addPart(source) {
    try {
        parts.push(await preparePhoto(source));
    } catch {
        toast('Couldn’t read that photo. Try again.');
        return;
    }
    if (twoParts() && parts.length === 1) {
        status('Top part saved. Now the bottom part.', 'ok');
        return;
    }
    finish();
}

function finish() {
    const taken = parts.slice(0, 2);
    close();
    if (taken.length) onDone(taken);
}

function close() {
    stopStream();
    if (dlg().open) dlg().close();
}

/**
 * Open the camera. `done` receives one or two JPEG blobs.
 *
 * @param {(blobs: Blob[]) => void} done
 */
export function openCamera(done) {
    onDone = done;
    parts = [];
    $('camTwo').setAttribute('aria-pressed', 'false');
    $('camFlash').setAttribute('aria-pressed', 'false');
    status('');
    $('camView').classList.remove('no-camera');
    dlg().showModal();
    startStream();
}

export function bindCamera() {
    $('camClose').addEventListener('click', close);
    dlg().addEventListener('cancel', () => stopStream());
    dlg().addEventListener('close', () => stopStream());

    $('camShutter').addEventListener('click', () => {
        const video = $('camVideo');
        if (stream && video.videoWidth) {
            addPart(video);
            $('camView').classList.add('flash');
            setTimeout(() => $('camView').classList.remove('flash'), 150);
        } else {
            $('camCapture').click();
        }
    });

    $('camTwo').addEventListener('click', () => {
        const on = !twoParts();
        $('camTwo').setAttribute('aria-pressed', String(on));
        status(on ? (parts.length ? 'Now the bottom part.' : 'Start with the top part.') : '');
        if (!on && parts.length === 1) finish();
    });

    $('camFlash').addEventListener('click', async () => {
        const track = stream?.getVideoTracks()[0];
        if (!track) return;
        const on = $('camFlash').getAttribute('aria-pressed') !== 'true';
        try {
            await track.applyConstraints({ advanced: [{ torch: on }] });
            $('camFlash').setAttribute('aria-pressed', String(on));
        } catch {
            $('camFlash').hidden = true;
        }
    });

    $('camPhotos').addEventListener('click', () => $('camPick').click());

    // Photos: one or two images from the library. Camera fallback: one photo at a time.
    $('camPick').addEventListener('change', async (e) => {
        const files = [...e.target.files].slice(0, 2 - parts.length);
        e.target.value = '';
        for (const file of files) {
            parts.push(await preparePhoto(file).catch(() => null));
        }
        parts = parts.filter(Boolean);
        if (parts.length) finish();
    });
    $('camCapture').addEventListener('change', async (e) => {
        const [file] = e.target.files;
        e.target.value = '';
        if (file) await addPart(file);
    });
}
