<?php

declare(strict_types=1);

namespace App\Support;

/**
 * YouTube-/Vimeo-Link eines Profils (#13).
 *
 * Erkennt nur Hosts aus premium.profile.video_hosts und liefert die
 * Embed-URL der datensparsamen Variante (youtube-nocookie, Vimeo mit dnt=1).
 * Das Profil laedt den iframe erst nach Klick; vorher wird kein Inhalt vom
 * Anbieter geholt, deshalb gibt es bewusst keine Vorschaubild-URL.
 */
final class VideoEmbed
{
    public const YOUTUBE = 'youtube';

    public const VIMEO = 'vimeo';

    private function __construct(
        public readonly string $provider,
        public readonly string $videoId,
    ) {}

    public static function fromUrl(?string $url): ?self
    {
        $url = trim((string) $url);

        if ($url === '' || mb_strlen($url) > 500) {
            return null;
        }

        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));

        if (! in_array($scheme, ['http', 'https'], true)
            || ! in_array($host, (array) config('premium.profile.video_hosts', []), true)) {
            return null;
        }

        $path = (string) ($parts['path'] ?? '');

        if (str_contains($host, 'vimeo.com')) {
            return preg_match('~^/(?:video/)?(\d{6,12})/?$~', $path, $match) === 1
                ? new self(self::VIMEO, $match[1])
                : null;
        }

        $id = null;

        if ($host === 'youtu.be') {
            $id = ltrim($path, '/');
        } elseif ($path === '/watch') {
            parse_str((string) ($parts['query'] ?? ''), $query);
            $id = is_string($query['v'] ?? null) ? $query['v'] : null;
        } elseif (preg_match('~^/(?:embed|shorts|live)/([^/]+)/?$~', $path, $match) === 1) {
            $id = $match[1];
        }

        return is_string($id) && preg_match('/^[A-Za-z0-9_-]{11}$/', $id) === 1
            ? new self(self::YOUTUBE, $id)
            : null;
    }

    public static function isValid(?string $url): bool
    {
        return self::fromUrl($url) !== null;
    }

    public function embedUrl(): string
    {
        return match ($this->provider) {
            self::VIMEO => "https://player.vimeo.com/video/{$this->videoId}?dnt=1&autoplay=1",
            default => "https://www.youtube-nocookie.com/embed/{$this->videoId}?autoplay=1&rel=0",
        };
    }

    public function watchUrl(): string
    {
        return match ($this->provider) {
            self::VIMEO => "https://vimeo.com/{$this->videoId}",
            default => "https://www.youtube.com/watch?v={$this->videoId}",
        };
    }

    public function providerLabel(): string
    {
        return match ($this->provider) {
            self::VIMEO => 'Vimeo',
            default => 'YouTube',
        };
    }

    /**
     * @return array{provider: string, provider_label: string, embed_url: string, watch_url: string}
     */
    public function toArray(): array
    {
        return [
            'provider' => $this->provider,
            'provider_label' => $this->providerLabel(),
            'embed_url' => $this->embedUrl(),
            'watch_url' => $this->watchUrl(),
        ];
    }
}
