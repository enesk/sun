<?php

declare(strict_types=1);

namespace App\Filament\Central\Resources\PreviewLinkResource\Pages;

use App\Filament\Central\Resources\PreviewLinkResource;
use App\Preview\PreviewTokenService;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

/**
 * Anlegen eines Vorschau-Links. Nach dem Speichern bleibt die Seite stehen
 * und zeigt die URLs im Dialog; erst „Fertig" fuehrt zur Liste. So verlaesst
 * der Klartext-Token den Livewire-State dieser Seite nie.
 */
class CreatePreviewLink extends CreateRecord
{
    protected static string $resource = PreviewLinkResource::class;

    protected static bool $canCreateAnother = false;

    private ?string $issuedToken = null;

    protected function handleRecordCreation(array $data): Model
    {
        $issued = app(PreviewTokenService::class)->issue(
            name: (string) $data['name'],
            expiresAt: PreviewLinkResource::expiresAt($data['expires_on']),
            allTenants: (bool) ($data['applies_to_all_tenants'] ?? false),
            tenantIds: array_map('intval', $data['tenant_ids'] ?? []),
            createdBy: auth()->id(),
        );

        $this->issuedToken = $issued['token'];

        return $issued['link'];
    }

    protected function afterCreate(): void
    {
        $this->mountAction('previewUrls', [
            'record' => $this->getRecord()->getKey(),
            'token' => $this->issuedToken,
        ]);

        $this->issuedToken = null;

        // Keine Weiterleitung: der Dialog muss auf dieser Seite aufgehen.
        // Die Transaktion wird trotzdem festgeschrieben.
        $this->halt();
    }

    public function previewUrlsAction(): Action
    {
        return PreviewLinkResource::urlsAction()
            ->closeModalByClickingAway(false)
            ->closeModalByEscaping(false)
            ->modalCloseButton(false)
            ->modalCancelAction(false)
            ->modalSubmitAction(fn (Action $action): Action => $action->label(__('Fertig')))
            ->action(fn () => $this->redirect(PreviewLinkResource::getUrl('index')));
    }
}
