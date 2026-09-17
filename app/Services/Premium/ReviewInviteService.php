<?php

declare(strict_types=1);

namespace App\Services\Premium;

use App\Models\Portal\Company;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/**
 * Bewertungslink, QR-Code und Widget-Einbettung eines Betriebs (#12).
 *
 * Der QR-Code entsteht lokal mit bacon/bacon-qr-code als SVG, ohne externe Dienste.
 */
class ReviewInviteService
{
    public const REF_QR = 'qr';

    private const QR_SIZE = 512;

    private const QR_MARGIN = 2;

    /**
     * Oeffentlich erreichbarer, aktiver Betrieb zu einem Slug im Format {id}-{slug}.
     */
    public function resolveCompany(string $companySlug): ?Company
    {
        $company = Company::findByUrlSlug($companySlug);

        return $company !== null && $company->is_active ? $company : null;
    }

    public function inviteUrl(Company $company, ?string $ref = null): string
    {
        return route('portal.reviews.invite', array_filter([
            'companySlug' => $company->url_slug,
            'ref' => $ref,
        ]));
    }

    /**
     * Ziel des Bewertungslinks: Profil mit geoeffnetem Bewertungsformular.
     */
    public function formUrl(Company $company, ?string $ref = null): string
    {
        $query = http_build_query(array_filter([
            'bewerten' => 1,
            'ref' => $ref,
        ]));

        return "{$company->portal_url}?{$query}#bewertungen";
    }

    public function qrSvg(Company $company): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle(self::QR_SIZE, self::QR_MARGIN),
            new SvgImageBackEnd,
        );

        return (new Writer($renderer))->writeString($this->inviteUrl($company, self::REF_QR));
    }

    public function qrFilename(Company $company): string
    {
        return "bewertungslink-{$company->url_slug}.svg";
    }

    public function widgetUrl(Company $company): string
    {
        return route('portal.reviews.widget', ['companySlug' => $company->url_slug]);
    }

    public function widgetScriptUrl(Company $company): string
    {
        return route('portal.reviews.widget.script', ['companySlug' => $company->url_slug]);
    }

    public function embedCode(Company $company): string
    {
        $src = e($this->widgetScriptUrl($company));

        return "<script src=\"{$src}\" async></script>";
    }
}
