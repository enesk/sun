<?php

namespace App\Http\Controllers\Portal;

use App\Guide\Seo\LlmsTxtBuilder;
use App\Http\Controllers\Controller;
use Illuminate\Http\Response;

/**
 * /llms.txt und /llms-full.txt je Mandant (#18).
 *
 * Beide Antworten sind text/plain und kommen aus dem Cache des
 * LlmsTxtBuilder; die Cache-Dauer steht auch im Cache-Control-Header, damit
 * Zwischenspeicher der Crawler dieselbe Frist verwenden.
 */
class LlmsTxtController extends Controller
{
    public function __construct(
        private readonly LlmsTxtBuilder $builder,
    ) {}

    public function index(): Response
    {
        return $this->plain($this->builder->index());
    }

    public function full(): Response
    {
        return $this->plain($this->builder->full());
    }

    private function plain(string $content): Response
    {
        return response($content, 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Cache-Control' => 'public, max-age='.LlmsTxtBuilder::CACHE_TTL,
            'X-Robots-Tag' => 'noindex',
        ]);
    }
}
