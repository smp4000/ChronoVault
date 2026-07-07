<?php

/**
 * =========================================================================
 * TenantMediaUrlGenerator — Media-URLs über die stancl-Asset-Route
 * =========================================================================
 *
 * Zweck:
 *   Die Media Library speichert auf der public-Disk, deren Root der
 *   FilesystemTenancyBootstrapper pro Tenant verschiebt
 *   (storage/tenant{id}/app/public). Der Standard-UrlGenerator baut
 *   URLs über Storage::url() → /storage/... — das zeigt auf den
 *   ZENTRALEN storage-Symlink und wäre weder tenant-korrekt noch
 *   isoliert. Stattdessen: tenant_asset() → /tenancy/assets/{pfad},
 *   ausgeliefert vom TenantAssetsController im Tenant-Kontext.
 *
 * Registriert in config/media-library.php → url_generator.
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Support\MediaLibrary;

use Spatie\MediaLibrary\Support\UrlGenerator\DefaultUrlGenerator;

class TenantMediaUrlGenerator extends DefaultUrlGenerator
{
    public function getUrl(): string
    {
        return tenant_asset($this->getPathRelativeToRoot());
    }
}
