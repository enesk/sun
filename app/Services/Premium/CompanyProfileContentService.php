<?php

declare(strict_types=1);

namespace App\Services\Premium;

use App\Enums\PremiumFeature;
use App\Models\Portal\Company;
use App\Models\Portal\CompanyReference;
use App\Models\Portal\CompanyService;
use App\Support\VideoEmbed;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Profil-Ausbau (#13): Galerie, Video, Projektreferenzen, Leistungskatalog.
 *
 * Limits kommen ausschliesslich aus CompanyEntitlementService. Nach einem
 * Downgrade bleiben alle Galeriefotos gespeichert; oeffentlich erscheinen
 * nur die ersten N nach order_column, der Rest ist im Betriebsbereich als
 * nicht sichtbar markiert. Referenzen und Leistungen bleiben ebenfalls
 * gespeichert und werden ohne Freischaltung nur nicht ausgespielt.
 */
class CompanyProfileContentService
{
    public const GALLERY = 'gallery';

    public function __construct(
        private readonly CompanyEntitlementService $entitlements,
    ) {}

    // ── Galerie ──

    public function galleryLimit(Company $company): ?int
    {
        return $this->entitlements->limit($company, PremiumFeature::GalleryPhotos);
    }

    /**
     * Freie Upload-Plaetze; null bedeutet unbegrenzt.
     */
    public function remainingGallerySlots(Company $company): ?int
    {
        $limit = $this->galleryLimit($company);

        return $limit === null ? null : max(0, $limit - $this->allGallery($company)->count());
    }

    /**
     * Fotos in Sortierreihenfolge, die das Profil zeigen darf.
     *
     * @return Collection<int, Media>
     */
    public function visibleGallery(Company $company): Collection
    {
        $limit = $this->galleryLimit($company);
        $media = $this->allGallery($company);

        return $limit === null ? $media : $media->take($limit)->values();
    }

    /**
     * Alle Fotos fuer den Betriebsbereich, je Foto mit Sichtbarkeit.
     *
     * @return list<array{id: int, url: string, name: string, size: string, visible: bool}>
     */
    public function galleryOverview(Company $company): array
    {
        $limit = $this->galleryLimit($company);

        return $this->allGallery($company)->values()->map(fn (Media $media, int $index): array => [
            'id' => (int) $media->getKey(),
            'url' => $media->getUrl('medium'),
            'name' => (string) $media->file_name,
            'size' => (string) $media->human_readable_size,
            'visible' => $limit === null || $index < $limit,
        ])->all();
    }

    /**
     * Speichert Uploads bis zum Limit und gibt die Zahl der gespeicherten
     * Fotos zurueck. Ueberzaehlige Dateien werden verworfen.
     *
     * @param  iterable<UploadedFile>  $files
     */
    public function addGalleryPhotos(Company $company, iterable $files): int
    {
        $remaining = $this->remainingGallerySlots($company);
        $added = 0;

        foreach ($files as $file) {
            if ($remaining !== null && $added >= $remaining) {
                break;
            }

            $company->addMedia($file->getRealPath())
                ->usingFileName('gallery_'.uniqid().'.'.$file->getClientOriginalExtension())
                ->toMediaCollection(self::GALLERY);
            $added++;
        }

        if ($added > 0) {
            $company->unsetRelation('media');
        }

        return $added;
    }

    // ── Video ──

    public function video(Company $company): ?VideoEmbed
    {
        if (! $this->entitlements->can($company, PremiumFeature::VideoEmbed)) {
            return null;
        }

        return VideoEmbed::fromUrl($company->getAttribute('video_url'));
    }

    // ── Projektreferenzen ──

    public function referencesLimit(Company $company): ?int
    {
        return $this->entitlements->limit($company, PremiumFeature::References);
    }

    public function canAddReference(Company $company): bool
    {
        $limit = $this->referencesLimit($company);

        return $limit === null || $company->references()->count() < $limit;
    }

    /**
     * Referenzen fuer das Profil, auf das Limit gekuerzt.
     *
     * @return EloquentCollection<int, CompanyReference>
     */
    public function visibleReferences(Company $company): EloquentCollection
    {
        $limit = $this->referencesLimit($company);

        if ($limit === 0) {
            return new EloquentCollection;
        }

        return $company->references()
            ->with('media')
            ->when($limit !== null, fn ($query) => $query->limit($limit))
            ->get();
    }

    public function referencePhotosMax(): int
    {
        return (int) config('premium.profile.reference_photos_max', 5);
    }

    // ── Leistungskatalog ──

    public function canUseServiceCatalog(Company $company): bool
    {
        return $this->entitlements->can($company, PremiumFeature::ServiceCatalog);
    }

    public function servicesMax(): int
    {
        return (int) config('premium.profile.services_max', 50);
    }

    /**
     * @return EloquentCollection<int, CompanyService>
     */
    public function visibleServices(Company $company): EloquentCollection
    {
        if (! $this->canUseServiceCatalog($company)) {
            return new EloquentCollection;
        }

        return $company->relationLoaded('services')
            ? $company->getRelation('services')
            : $company->services()->get();
    }

    /**
     * @return Collection<int, Media>
     */
    private function allGallery(Company $company): Collection
    {
        // getMedia() sortiert nach order_column und nutzt geladene media-Relation
        return $company->getMedia(self::GALLERY);
    }
}
