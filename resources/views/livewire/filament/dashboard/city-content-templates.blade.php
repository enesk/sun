<div>
    <form wire:submit="save">
        {{ $this->form }}

        <div class="pt-4 flex gap-4">
            <x-filament::button type="submit">
                <x-filament::loading-indicator class="h-5 w-5 inline" wire:loading />
                Speichern
            </x-filament::button>
        </div>
    </form>

    <x-filament-actions::modals />
</div>
