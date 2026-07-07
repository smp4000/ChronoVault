# Modul 4 — Medienverwaltung (Fotos, Zertifikate, Dokumente)

> Stand: 2026-07-07 · Status: ✅ Kern fertig (geführter Foto-Upload offen)

## Überblick

Medienverwaltung auf Basis von **spatie/laravel-medialibrary** +
Filament-Plugin — tenant-isoliert auf allen Ebenen:

- **media-Tabelle pro Tenant-DB** (Migration in `database/migrations/tenant/`,
  mit `uuidMorphs` statt `morphs` — unsere Entitäten haben UUID-PKs!)
- **Dateien auf der public-Disk**, deren Root der
  FilesystemTenancyBootstrapper pro Tenant verschiebt
- **URLs über `TenantMediaUrlGenerator`** (tenant_asset → stancl-Asset-Route)
  — der Standard-Generator zeigte auf den zentralen storage-Symlink

## Collections (Watch)

| Collection | Inhalt | MIME |
|---|---|---|
| `photos` | Uhrenfotos (KI-Download + manueller Upload, sortierbar) | jpeg/png/webp/avif/gif |
| `documents` | Zertifikate, Kaufbelege, Servicehefte | pdf + Bilder |

Die Collection prüft den ECHTEN Datei-MIME (Inhalt, nicht Header) —
Tests brauchen deshalb echte Bild-Bytes (tinyGif()-Helper).

## Livewire-Uploads auf Tenant-Domains (kritisch!)

Analog zur Update-Route (Modul-1-Nachtrag) brauchen auch die Upload-Routen
Tenancy — sonst 419 bzw. falscher Storage:

- **POST upload-file**: `config/livewire.php → temporary_file_upload.middleware`
  = `['universal', InitializeTenancyByDomain::class, 'throttle:60,1']`
- **GET preview-file**: `FilePreviewController::$middleware` wird im
  TenancyServiceProvider gesetzt (liest tenant-gesuffixten Temp-Storage)

## KI-Foto-Download (Umbau aus Modul 3)

`DownloadWatchPhotosAction` schreibt jetzt in die photos-Collection
(`addMediaFromString`, custom_properties: origin=ai_lookup, source_url).
Der `WatchObserver` lädt nur, wenn weder Media-Fotos noch Alt-Fotos
existieren. Die Alt-Spalte `watches.photos` bleibt als Fallback bis zur
Datenmigration:

```
php artisan tenants:run watches:migrate-photos
```

(idempotent; verschiebt Dateien in die Media-Struktur und nullt die Spalte).

## Filament

- Tab **„Fotos & Dokumente"** im WatchForm: `SpatieMediaLibraryFileUpload`
  für beide Collections (Fotos: Grid, sortierbar, max 20×10 MB;
  Dokumente: downloadbar/öffnbar, max 20×20 MB)
- Bestandsliste: `SpatieMediaLibraryImageColumn` (erstes Foto als Thumbnail)

## Offen (Modul-4-Rest)

- **Geführter Foto-Upload** (photo_slots aus der Vorgänger-App:
  Slot-Checkliste Front/Rücken/Schließe/…)
- Markenlogos (Brand-Collection)
- Bild-Conversions/Thumbnails (aktuell Originale; Conversions erst mit
  Queue-Worker sinnvoll — queue_conversions_by_default beachten)

## Stolperfallen (dokumentiert)

- Paket-Migrations-Stub nutzt `morphs` (BigInt) — für UUID-Entitäten
  `uuidMorphs` verwenden, sonst passen model_id-Werte nicht.
- `tenant_asset()` in CLI/Queue erzeugt URLs mit APP_URL (localhost) —
  im HTTP-Kontext korrekt mit Tenant-Host. Für E-Mails später absolute
  Tenant-URLs explizit bauen.
