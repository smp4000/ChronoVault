{{--
=============================================================================
CSS für das Wert-Zertifikat (Partial) — eingebunden in den <style>-Block
von pdf/certificate.blade.php UND pdf/inventory.blade.php (Mappe).
Alle Klassen mit cert-Präfix, damit sie nicht mit der Übersicht kollidieren.
=============================================================================
--}}
.cert-head { text-align: center; }
.cert-brand { font-size: 16pt; font-weight: bold; letter-spacing: 4px; text-transform: uppercase; }
.cert-brand-dot { color: #1e40af; }
.cert-brand-sub { font-size: 7.5pt; color: #71717a; margin-top: 3px; }
.cert-title { text-align: center; margin-top: 26px; }
.cert-title .cert-rule { font-size: 10pt; letter-spacing: 6px; color: #1e40af; font-weight: bold; }
.cert-title h1 { font-size: 14pt; letter-spacing: 3px; margin-top: 4px; }
table.cert-meta { width: 100%; border-collapse: collapse; margin-top: 26px; }
table.cert-meta td { vertical-align: top; font-size: 9pt; }
.cert-label { font-size: 7.5pt; text-transform: uppercase; letter-spacing: 1px; color: #71717a; }
.cert-box { border: 0.75pt solid #d4d4d8; border-radius: 8px; padding: 14px 16px; margin-top: 22px; }
table.cert-item { width: 100%; border-collapse: collapse; }
table.cert-item td { vertical-align: top; }
img.cert-hero { width: 170px; height: auto; border-radius: 6px; border: 0.5pt solid #e4e4e7; }
table.cert-kv { width: 100%; border-collapse: collapse; }
table.cert-kv td { font-size: 8.5pt; padding: 2.5px 8px 2.5px 0; }
table.cert-kv .k { color: #71717a; width: 42%; white-space: nowrap; }
.cert-value-row td { border-top: 0.75pt solid #18181b; padding-top: 6px !important; font-weight: bold; }
.cert-value-row .amount { color: #1e40af; font-size: 11pt; }
.cert-statement { margin-top: 22px; font-size: 8.5pt; line-height: 1.7; color: #3f3f46; }
table.cert-sign { width: 100%; border-collapse: collapse; margin-top: 44px; }
table.cert-sign td { width: 33%; padding: 0 14px; text-align: center; font-size: 8pt; color: #71717a; }
table.cert-sign .line { border-top: 0.75pt solid #18181b; padding-top: 5px; }
.cert-photo-page { page-break-before: always; }
table.cert-photo-grid { width: 100%; border-collapse: collapse; margin-top: 12px; }
table.cert-photo-grid td { width: 50%; padding: 0 8px 12px 0; text-align: center; vertical-align: top; }
table.cert-photo-grid img { width: 100%; max-width: 300px; height: auto; border-radius: 6px; border: 0.5pt solid #e4e4e7; }
table.cert-photo-grid .plabel { font-size: 7pt; color: #71717a; margin-top: 3px; }
