<?php

namespace App\Http\Controllers\Verwaltung;

use App\Constants\TenancyPermissionConstants;
use App\Models\Portal\Post;
use App\Services\TenantPermissionService;

class VerwaltungBlogController extends VerwaltungBaseController
{
    public function __construct(TenantPermissionService $permissionService)
    {
        parent::__construct($permissionService);
    }

    public function index()
    {
        $this->requirePermission(TenancyPermissionConstants::PERMISSION_UPDATE_TENANT_SETTINGS);

        $this->setBreadcrumbs([
            ['label' => 'Ratgeber'],
        ]);

        $navigationItems = $this->getNavigationItems();

        return view('pages.verwaltung.blog.index', compact('navigationItems'));
    }

    public function create()
    {
        $this->requirePermission(TenancyPermissionConstants::PERMISSION_UPDATE_TENANT_SETTINGS);

        $this->setBreadcrumbs([
            ['label' => 'Ratgeber', 'url' => route('verwaltung.blog.index')],
            ['label' => 'Neuer Artikel'],
        ]);

        $navigationItems = $this->getNavigationItems();

        return view('pages.verwaltung.blog.create', compact('navigationItems'));
    }

    public function edit(int $id)
    {
        $this->requirePermission(TenancyPermissionConstants::PERMISSION_UPDATE_TENANT_SETTINGS);

        $post = Post::with(['category', 'tags'])->findOrFail($id);

        $this->setBreadcrumbs([
            ['label' => 'Ratgeber', 'url' => route('verwaltung.blog.index')],
            ['label' => $post->title],
        ]);

        $navigationItems = $this->getNavigationItems();

        return view('pages.verwaltung.blog.edit', compact('navigationItems', 'post'));
    }

    public function destroy(int $id)
    {
        $this->requirePermission(TenancyPermissionConstants::PERMISSION_UPDATE_TENANT_SETTINGS);

        $post = Post::findOrFail($id);
        $title = $post->title;

        $post->tags()->detach();
        $post->clearMediaCollection('featured_image');
        $post->delete();

        return redirect()->route('verwaltung.blog.index')
            ->with('success', "Artikel \"{$title}\" wurde gelöscht.");
    }

    // ── Kategorien ──

    public function categories()
    {
        $this->requirePermission(TenancyPermissionConstants::PERMISSION_UPDATE_TENANT_SETTINGS);

        $this->setBreadcrumbs([
            ['label' => 'Ratgeber', 'url' => route('verwaltung.blog.index')],
            ['label' => 'Kategorien'],
        ]);

        $navigationItems = $this->getNavigationItems();

        return view('pages.verwaltung.blog.categories', compact('navigationItems'));
    }

    // ── Tags ──

    public function tags()
    {
        $this->requirePermission(TenancyPermissionConstants::PERMISSION_UPDATE_TENANT_SETTINGS);

        $this->setBreadcrumbs([
            ['label' => 'Ratgeber', 'url' => route('verwaltung.blog.index')],
            ['label' => 'Tags'],
        ]);

        $navigationItems = $this->getNavigationItems();

        return view('pages.verwaltung.blog.tags', compact('navigationItems'));
    }
}
