<?php

declare(strict_types=1);

namespace App\Preview;

use App\Models\Tenant;
use App\Preview\Models\PreviewLink;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * Token, Pruefung und Cookie der Vorschau-Links (SUN-PV-001, Ticket #2).
 *
 * Der Klartext-Token gibt es nur als Rueckgabe von {@see issue()} und im
 * verschluesselten Cookie des Besuchers — gespeichert wird der sha256-Hash.
 */
class PreviewTokenService
{
    public const COOKIE = 'sun_preview';

    public const TOKEN_LENGTH = 40;

    /** Sekunden, die das Pruefergebnis der Middleware im Cache liegt. */
    public const CACHE_TTL = 60;

    /**
     * Legt einen Link an und liefert den Klartext-Token genau einmal zurueck.
     *
     * @param  array<int, int>  $tenantIds  nur ausgewertet, wenn nicht $allTenants
     * @return array{link: PreviewLink, token: string}
     */
    public function issue(
        string $name,
        Carbon $expiresAt,
        bool $allTenants,
        array $tenantIds = [],
        ?int $createdBy = null,
    ): array {
        $token = Str::random(self::TOKEN_LENGTH);

        $link = DB::connection((new PreviewLink)->getConnectionName())->transaction(function () use ($name, $token, $expiresAt, $allTenants, $tenantIds, $createdBy) {
            $link = PreviewLink::create([
                'name' => $name,
                'token_hash' => $this->hash($token),
                'applies_to_all_tenants' => $allTenants,
                'expires_at' => $expiresAt,
                'created_by' => $createdBy,
            ]);

            if (! $allTenants) {
                $link->tenants()->sync($tenantIds);
            }

            return $link;
        });

        return ['link' => $link, 'token' => $token];
    }

    /**
     * Neuer Token fuer denselben Link (Ticket #3); der alte ist danach
     * ungueltig. Liefert den Klartext-Token genau einmal zurueck.
     */
    public function regenerate(PreviewLink $link): string
    {
        $token = Str::random(self::TOKEN_LENGTH);

        $link->forceFill(['token_hash' => $this->hash($token)])->save();

        $this->forgetCached($link);

        return $token;
    }

    /**
     * Verwirft das 60-Sekunden-Pruefergebnis aus {@see isValidForTenant()}
     * auf allen Portalen, fuer die der Link gilt (Ticket #3).
     *
     * Der Dateicache liegt je Portal im Tenant-Storage, deshalb wird er im
     * Kontext jedes Portals geleert. Ein Cookie und damit ein Cache-Eintrag
     * entsteht nur auf Portalen, fuer die der Link gilt.
     */
    public function forgetCached(PreviewLink $link): void
    {
        $tenants = $link->applies_to_all_tenants ? Tenant::all() : $link->tenants()->get();

        /** @var Tenant $tenant */
        foreach ($tenants as $tenant) {
            $key = "preview_link:{$link->getKey()}:tenant:{$tenant->getKey()}";

            $tenant->run(fn () => Cache::forget($key));
        }
    }

    public function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * Gueltiger Link zu diesem Token, der fuer das Portal gilt — sonst null.
     */
    public function findForTenant(string $token, Tenant $tenant): ?PreviewLink
    {
        return PreviewLink::query()
            ->where('token_hash', $this->hash($token))
            ->valid()
            ->forTenant($tenant)
            ->first();
    }

    /**
     * Prueft den Cookie-Inhalt gegen die Central-DB, 60 Sekunden gecacht.
     *
     * Gecacht wird der Token-Hash des gueltigen Links (leer, wenn ungueltig);
     * verglichen wird bei jedem Request mit dem Hash aus dem Cookie. Der Key
     * enthaelt Link- und Tenant-ID, weil die Geltung je Portal verschieden ist.
     */
    public function isValidForTenant(int $linkId, string $token, Tenant $tenant): bool
    {
        $key = "preview_link:{$linkId}:tenant:{$tenant->getKey()}";

        $validHash = Cache::remember($key, self::CACHE_TTL, function () use ($linkId, $tenant): string {
            $hash = PreviewLink::query()
                ->whereKey($linkId)
                ->valid()
                ->forTenant($tenant)
                ->value('token_hash');

            return (string) $hash;
        });

        return $validHash !== '' && hash_equals($validHash, $this->hash($token));
    }

    public function recordUse(PreviewLink $link): void
    {
        // Ohne Model-Events und ohne updated_at: die Nutzung ist keine
        // Aenderung am Link.
        PreviewLink::query()->whereKey($link->getKey())->update([
            'last_used_at' => now(),
            'use_count' => DB::raw('use_count + 1'),
        ]);
    }

    /**
     * @return array{id: int, token: string}|null
     */
    public function readCookie(Request $request): ?array
    {
        $raw = $request->cookie(self::COOKIE);

        if (! is_string($raw) || $raw === '') {
            return null;
        }

        $data = json_decode($raw, true);

        if (! is_array($data) || ! is_int($data['id'] ?? null) || ! is_string($data['token'] ?? null)) {
            return null;
        }

        return ['id' => $data['id'], 'token' => $data['token']];
    }

    /**
     * Cookie je Domain; verschluesselt wird es von EncryptCookies
     * (sun_preview steht bewusst nicht in der Ausnahmeliste).
     */
    public function makeCookie(Request $request, PreviewLink $link, string $token): Cookie
    {
        $minutes = max(1, (int) ceil(now()->diffInSeconds($link->expires_at) / 60));

        return cookie(
            self::COOKIE,
            json_encode(['id' => $link->getKey(), 'token' => $token]),
            $minutes,
            '/',
            null,
            $this->secure($request),
            true,
            false,
            'lax',
        );
    }

    public function forgetCookie(Request $request): Cookie
    {
        return cookie(self::COOKIE, '', -2628000, '/', null, $this->secure($request), true, false, 'lax');
    }

    /**
     * Immer Secure, ausser lokal ueber http (*.test ohne TLS) — sonst
     * schickte der Browser das Cookie dort nie zurueck.
     */
    private function secure(Request $request): bool
    {
        return $request->isSecure() || ! app()->environment('local');
    }
}
