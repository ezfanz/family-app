# E-Itinerary app icons

| File | Size | Use |
|---|---|---|
| icon.svg | vector | Favicon for modern browsers, in-app header logo |
| favicon.ico | 16, 32, 48 | Older browsers |
| favicon-16.png / favicon-32.png / favicon-48.png | 16–48 px | Browser tabs |
| apple-touch-icon.png | 180 px, square | iPhone home screen (iOS rounds the corners) |
| icon-192.png / icon-512.png | 192, 512 px | PWA manifest, Android |
| icon-maskable-512.png | 512 px, square, padded | Android adaptive icon |
| icon-1024.png | 1024 px, square | App Store / Play Store later |
| icon-square.svg / icon-maskable.svg | vector | Sources for the square versions |

## Paste into `<head>`

```html
<link rel="icon" href="/icons/icon.svg" type="image/svg+xml">
<link rel="icon" href="/icons/favicon.ico" sizes="any">
<link rel="apple-touch-icon" href="/icons/apple-touch-icon.png">
<link rel="manifest" href="/manifest.webmanifest">
<meta name="theme-color" content="#2A3B8F">
```

## manifest.webmanifest

```json
{
  "name": "E-Itinerary",
  "short_name": "E-Itinerary",
  "start_url": "/",
  "display": "standalone",
  "background_color": "#F7F7F5",
  "theme_color": "#2A3B8F",
  "icons": [
    { "src": "/icons/icon-192.png", "sizes": "192x192", "type": "image/png" },
    { "src": "/icons/icon-512.png", "sizes": "512x512", "type": "image/png" },
    { "src": "/icons/icon-maskable-512.png", "sizes": "512x512", "type": "image/png", "purpose": "maskable" }
  ]
}
```

Colours: indigo #2A3B8F, globe #9DAEFF, plane and orbit #FFFFFF.
