<?php

namespace App\Constants;

/**
 * Zentrale Event-Namen für alle Livewire-Components.
 * Verhindert Magic Strings und macht Event-Flow nachvollziehbar.
 */
final class LivewireEventConstants
{
    // ─── Toast Notifications ───────────────────────────────────
    public const TOAST = 'toast';

    // Toast Types
    public const TOAST_SUCCESS = 'success';
    public const TOAST_ERROR = 'error';
    public const TOAST_INFO = 'info';
    public const TOAST_WARNING = 'warning';
}
