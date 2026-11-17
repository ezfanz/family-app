/*
 * OpenCV.js, loaded only through scanner.js's dynamic import of this file.
 * The OpenCV module object is "thenable" and hands back itself, so it must
 * never be the value a promise resolves to (that loops forever). Exporting
 * it inside a plain object keeps import() from unwrapping it.
 */

import cv from '@techstark/opencv-js';

export const box = { cv };
