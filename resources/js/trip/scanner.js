/*
 * Adjust scan: after a snap, find the receipt's edges, let the person drag
 * the four corners, then flatten it and clean it up (grey, even contrast)
 * like a document scanner. OpenCV.js is about 10 MB, so it loads only here
 * and the service worker keeps it for offline use. If it can't load, the
 * original photo is kept: a receipt is never lost to the scanner.
 */

import { $, toast } from './util.js';

const MAX_EDGE = 1600;
const DETECT_EDGE = 640;
const QUALITY = 0.85;
const DEFAULT_QUAD = [[0.06, 0.04], [0.94, 0.04], [0.94, 0.96], [0.06, 0.96]];

let cvPromise = null;

/** Load OpenCV once; resolves to { cv } (never cv itself, see below). */
export function loadCv() {
    if (!cvPromise) {
        cvPromise = import('./opencv-loader.js')
            .then(async ({ box }) => {
                let cv = box.cv;
                // The Emscripten module is "thenable" and hands back itself. Wrap it in a plain
                // object: resolving a promise with a thenable would call `then` again, forever.
                if (typeof cv?.then === 'function' && !cv.Mat) {
                    cv = (await new Promise((resolve) => cv.then((ready) => resolve({ ready })))).ready;
                }
                if (!cv.Mat) await new Promise((resolve) => { cv.onRuntimeInitialized = resolve; });
                return { cv };
            })
            .catch((error) => {
                cvPromise = null;
                throw error;
            });
    }
    return cvPromise;
}

/** Start loading OpenCV in the background (e.g. when the Receipts tab opens). */
export function warmScanner() {
    loadCv().catch(() => {});
}

const withTimeout = (promise, ms) => Promise.race([promise, new Promise((_, reject) => setTimeout(() => reject(new Error('timeout')), ms))]);

function blobToImage(blob) {
    return new Promise((resolve, reject) => {
        const img = new Image();
        img.onload = () => resolve(img);
        img.onerror = () => reject(new Error('Could not open the photo'));
        img.src = URL.createObjectURL(blob);
    });
}

function canvasOf(img, maxEdge) {
    const scale = Math.min(1, maxEdge / Math.max(img.naturalWidth, img.naturalHeight));
    const canvas = document.createElement('canvas');
    canvas.width = Math.round(img.naturalWidth * scale);
    canvas.height = Math.round(img.naturalHeight * scale);
    canvas.getContext('2d').drawImage(img, 0, 0, canvas.width, canvas.height);
    return canvas;
}

/** Corners as top-left, top-right, bottom-right, bottom-left. */
function order(points) {
    const bySum = [...points].sort((a, b) => (a[0] + a[1]) - (b[0] + b[1]));
    const byDiff = [...points].sort((a, b) => (a[1] - a[0]) - (b[1] - b[0]));
    return [bySum[0], byDiff[0], bySum[3], byDiff[3]];
}

/**
 * Find the receipt: the biggest four-sided outline, or the tilted box around
 * the biggest shape. Returns corners as fractions of the width and height.
 */
export function detectQuad(cv, img) {
    const canvas = canvasOf(img, DETECT_EDGE);
    const { width, height } = canvas;
    const mats = [];
    const keep = (mat) => { mats.push(mat); return mat; };
    try {
        const src = keep(cv.imread(canvas));
        const gray = keep(new cv.Mat());
        const edges = keep(new cv.Mat());
        cv.cvtColor(src, gray, cv.COLOR_RGBA2GRAY);
        cv.GaussianBlur(gray, gray, new cv.Size(5, 5), 0);
        cv.Canny(gray, edges, 40, 140);
        const kernel = keep(cv.Mat.ones(5, 5, cv.CV_8U));
        cv.dilate(edges, edges, kernel);
        const contours = keep(new cv.MatVector());
        const hierarchy = keep(new cv.Mat());
        cv.findContours(edges, contours, hierarchy, cv.RETR_EXTERNAL, cv.CHAIN_APPROX_SIMPLE);

        const minArea = width * height * 0.12;
        let best = null;
        let bestArea = 0;
        let biggest = null;
        let biggestArea = 0;
        for (let i = 0; i < contours.size(); i++) {
            const contour = contours.get(i);
            const area = cv.contourArea(contour);
            if (area > biggestArea) {
                biggest?.delete();
                biggest = contour;
                biggestArea = area;
            }
            if (area >= minArea && area > bestArea) {
                const approx = new cv.Mat();
                cv.approxPolyDP(contour, approx, 0.02 * cv.arcLength(contour, true), true);
                if (approx.rows === 4 && cv.isContourConvex(approx)) {
                    best = [0, 1, 2, 3].map((k) => [approx.data32S[k * 2], approx.data32S[k * 2 + 1]]);
                    bestArea = area;
                }
                approx.delete();
            }
            if (contour !== biggest) contour.delete();
        }
        if (!best && biggest && biggestArea >= minArea) {
            const box = cv.minAreaRect(biggest);
            best = cv.RotatedRect.points(box).map((p) => [p.x, p.y]);
        }
        biggest?.delete();
        if (!best) return null;
        return order(best).map(([x, y]) => [Math.min(1, Math.max(0, x / width)), Math.min(1, Math.max(0, y / height))]);
    } finally {
        mats.forEach((mat) => mat.delete());
    }
}

const dist = (a, b) => Math.hypot(a[0] - b[0], a[1] - b[1]);

/** Flatten the area inside the corners and even out the contrast. */
export async function flatten(cv, img, quad) {
    const canvas = canvasOf(img, 2400);
    const pts = quad.map(([x, y]) => [x * canvas.width, y * canvas.height]);
    const [tl, tr, br, bl] = pts;
    let outW = Math.max(dist(tl, tr), dist(bl, br));
    let outH = Math.max(dist(tl, bl), dist(tr, br));
    const scale = Math.min(1, MAX_EDGE / Math.max(outW, outH));
    outW = Math.max(1, Math.round(outW * scale));
    outH = Math.max(1, Math.round(outH * scale));

    const mats = [];
    const keep = (mat) => { mats.push(mat); return mat; };
    try {
        const src = keep(cv.imread(canvas));
        const from = keep(cv.matFromArray(4, 1, cv.CV_32FC2, pts.flat()));
        const to = keep(cv.matFromArray(4, 1, cv.CV_32FC2, [0, 0, outW, 0, outW, outH, 0, outH]));
        const matrix = keep(cv.getPerspectiveTransform(from, to));
        const flat = keep(new cv.Mat());
        cv.warpPerspective(src, flat, matrix, new cv.Size(outW, outH), cv.INTER_LINEAR, cv.BORDER_REPLICATE);
        const gray = keep(new cv.Mat());
        cv.cvtColor(flat, gray, cv.COLOR_RGBA2GRAY);
        const clean = keep(new cv.Mat());
        if (typeof cv.CLAHE === 'function') {
            const clahe = new cv.CLAHE(2.0, new cv.Size(8, 8));
            clahe.apply(gray, clean);
            clahe.delete();
        } else {
            cv.normalize(gray, clean, 0, 255, cv.NORM_MINMAX);
        }
        const out = document.createElement('canvas');
        cv.imshow(out, clean);
        return await new Promise((resolve, reject) => out.toBlob((blob) => (blob ? resolve(blob) : reject(new Error('Could not save the scan'))), 'image/jpeg', QUALITY));
    } finally {
        mats.forEach((mat) => mat.delete());
    }
}

/* ---------- Adjust scan screen ---------- */

const dlg = () => $('scanDlg');
let quad = DEFAULT_QUAD.map((p) => [...p]);
let current = null; // { img, resolve, original }

function drawQuad() {
    const pct = quad.map(([x, y]) => `${(x * 100).toFixed(2)},${(y * 100).toFixed(2)}`).join(' ');
    $('scanShade').setAttribute('d', `M0,0H100V100H0Z M${pct.split(' ').join(' L')}Z`);
    $('scanEdge').setAttribute('points', pct);
    document.querySelectorAll('#scanFrame .scan-handle').forEach((handle, i) => {
        handle.style.left = `${quad[i][0] * 100}%`;
        handle.style.top = `${quad[i][1] * 100}%`;
    });
}

/** Size the frame to the photo's shape inside the free space. */
function fitFrame() {
    if (!current) return;
    const stage = $('scanStage');
    const ratio = current.img.naturalWidth / current.img.naturalHeight;
    let w = stage.clientWidth;
    let h = w / ratio;
    if (h > stage.clientHeight) {
        h = stage.clientHeight;
        w = h * ratio;
    }
    $('scanFrame').style.width = `${Math.floor(w)}px`;
    $('scanFrame').style.height = `${Math.floor(h)}px`;
}

function status(text) {
    $('scanStatus').textContent = text;
    $('scanStatus').hidden = !text;
}

function bindHandles() {
    const frame = $('scanFrame');
    frame.querySelectorAll('.scan-handle').forEach((handle, i) => {
        handle.addEventListener('pointerdown', (e) => {
            e.preventDefault();
            handle.setPointerCapture(e.pointerId);
            handle.classList.add('dragging');
        });
        handle.addEventListener('pointermove', (e) => {
            if (!handle.hasPointerCapture(e.pointerId)) return;
            const rect = frame.getBoundingClientRect();
            quad[i] = [Math.min(1, Math.max(0, (e.clientX - rect.left) / rect.width)), Math.min(1, Math.max(0, (e.clientY - rect.top) / rect.height))];
            drawQuad();
        });
        const end = (e) => {
            if (handle.hasPointerCapture(e.pointerId)) handle.releasePointerCapture(e.pointerId);
            handle.classList.remove('dragging');
        };
        handle.addEventListener('pointerup', end);
        handle.addEventListener('pointercancel', end);
        // Keyboard: arrows nudge the corner by 1%.
        handle.addEventListener('keydown', (e) => {
            const step = { ArrowLeft: [-0.01, 0], ArrowRight: [0.01, 0], ArrowUp: [0, -0.01], ArrowDown: [0, 0.01] }[e.key];
            if (!step) return;
            e.preventDefault();
            quad[i] = [Math.min(1, Math.max(0, quad[i][0] + step[0])), Math.min(1, Math.max(0, quad[i][1] + step[1]))];
            drawQuad();
        });
    });
}

function finish(blob) {
    const done = current;
    current = null;
    if (dlg().open) dlg().close();
    if (done) {
        URL.revokeObjectURL(done.img.src);
        done.resolve(blob);
    }
}

async function scanOne(blob, label) {
    const img = await blobToImage(blob).catch(() => null);
    if (!img) return blob;

    return new Promise((resolve) => {
        current = { img, resolve, original: blob };
        quad = DEFAULT_QUAD.map((p) => [...p]);
        $('scanImg').src = img.src;
        $('scanPart').textContent = label;
        $('scanPart').hidden = !label;
        $('scanDone').disabled = true;
        delete $('scanDone').dataset.plain;
        status('Finding the edges…');
        dlg().showModal();
        requestAnimationFrame(() => { fitFrame(); drawQuad(); });

        withTimeout(loadCv(), 20000)
            .then(({ cv }) => {
                if (current?.img !== img) return;
                let found = null;
                try {
                    found = detectQuad(cv, img);
                } catch {
                    found = null;
                }
                if (found) quad = found;
                drawQuad();
                status(found ? '' : 'Drag the corners to the edges of the receipt.');
                $('scanDone').disabled = false;
            })
            .catch(() => {
                if (current?.img !== img) return;
                status('The scanner needs internet the first time. Keeping the photo as it is.');
                $('scanDone').disabled = false;
                $('scanDone').dataset.plain = '1';
            });
    });
}

/**
 * Let the person adjust and clean each photo. Always resolves, with the scan
 * or (if they skip or the scanner can't run) the original photo.
 *
 * @param {Blob[]} blobs
 * @returns {Promise<Blob[]>}
 */
export async function scanPhotos(blobs) {
    const out = [];
    for (const [index, blob] of blobs.entries()) {
        out.push(await scanOne(blob, blobs.length > 1 ? `Part ${index + 1} of ${blobs.length}` : ''));
    }
    return out;
}

export function bindScanner() {
    bindHandles();
    window.addEventListener('resize', () => { if (dlg().open) fitFrame(); });
    $('scanOriginal').addEventListener('click', () => finish(current?.original));
    $('scanClose').addEventListener('click', () => finish(current?.original));
    dlg().addEventListener('cancel', (e) => { e.preventDefault(); finish(current?.original); });
    $('scanDone').addEventListener('click', async () => {
        if (!current) return;
        const button = $('scanDone');
        if (button.dataset.plain) {
            delete button.dataset.plain;
            finish(current.original);
            return;
        }
        button.disabled = true;
        status('Cleaning up the scan…');
        try {
            const { cv } = await loadCv();
            finish(await flatten(cv, current.img, quad));
        } catch {
            toast('Couldn’t clean up the scan. Kept the photo as it is.');
            finish(current?.original);
        }
    });
}
