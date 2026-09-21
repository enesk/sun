<?php

declare(strict_types=1);

namespace App\Guide\Llm;

use App\Guide\Llm\Exceptions\ProviderAccountException;
use App\Guide\Models\Central\ContentAlert;
use App\Guide\Models\Central\ProviderState;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Erkennt Antworten, die kein Fehler eines einzelnen Aufrufs sind, sondern
 * ein toter Zugang: erschoepftes Guthaben oder abgelehnter Schluessel (#104).
 *
 * Anthropic meldet beides mit HTTP 400/401 und `invalid_request_error` bzw.
 * `authentication_error` — fuer die Wiederholungslogik zaehlt das als Fehler
 * des Aufrufs und schlaegt sich sonst nirgends nieder. Dabei entsteht in
 * diesem Zustand gar kein Artikel mehr, die Uebersicht muss also rot sein.
 *
 * Deshalb: Provider auf ProviderState::STATUS_FAILED (nicht `degraded` und
 * nicht `paused` — kein Wiederholungsversuch und kein Mitternachtswechsel
 * hilft) und genau ein netzwerkweiter Alarm in Klartext, nicht einer je
 * Portal. Ein Probeaufruf nach `resilience.account_recheck_seconds` gibt den
 * Provider wieder frei, sobald jemand aufgeladen hat.
 */
class ProviderAccountGuard
{
    /**
     * Wortlaut der Meldung. Steht so im Alarmband der Uebersicht, in der
     * Alarmmail und im Block „Offene Alarme" des Tagesberichts.
     */
    public static function message(string $reason, string $provider): string
    {
        $isModelAccount = $provider === LlmClient::PROVIDER;

        if ($reason === ProviderAccountException::REASON_KEY) {
            return $isModelAccount
                ? __('Der Zugangsschlüssel des Modellkontos wird abgelehnt. Bis zur Erneuerung entstehen keine Artikel.')
                : __('Der Zugangsschlüssel des Kontos bei :provider wird abgelehnt. Bis zur Erneuerung entstehen keine Artikel.', ['provider' => $provider]);
        }

        return $isModelAccount
            ? __('Das Guthaben des Modellkontos ist aufgebraucht. Bis zur Aufladung entstehen keine Artikel.')
            : __('Das Guthaben des Kontos bei :provider ist aufgebraucht. Bis zur Aufladung entstehen keine Artikel.', ['provider' => $provider]);
    }

    /**
     * Ist diese Fehlantwort ein toter Zugang? Liefert den Grund oder null,
     * wenn es ein gewoehnlicher Fehler des Aufrufs ist.
     */
    public static function classify(Response $response): ?string
    {
        $status = $response->status();
        $body = (array) $response->json();
        $error = (array) ($body['error'] ?? []);
        $type = is_string($error['type'] ?? null) ? $error['type'] : '';
        $message = is_string($error['message'] ?? null) ? $error['message'] : $response->body();

        if ($status === 401 || $status === 403 || in_array($type, ['authentication_error', 'permission_error'], true)) {
            return ProviderAccountException::REASON_KEY;
        }

        if (preg_match('/credit balance|insufficient (credit|funds|balance)|billing|payment method/i', $message) === 1) {
            return ProviderAccountException::REASON_CREDIT;
        }

        return null;
    }

    /**
     * Haelt den toten Zugang fest und liefert die Ausnahme, die der Aufrufer
     * anstelle des HTTP-Fehlers wirft — damit auch der `chain_failed`-Alarm
     * Klartext traegt statt eines HTTP-Rumpfs.
     */
    public static function reportFailure(string $provider, string $reason, ?string $providerError = null): ProviderAccountException
    {
        $message = self::message($reason, $provider);
        $state = ProviderState::forProvider($provider);

        $meta = is_array($state->meta_json) ? $state->meta_json : [];
        $meta['account_reason'] = $reason;
        $meta['account_message'] = $message;
        $meta['account_failed_at'] = now()->toIso8601String();
        $meta['account_error'] = $providerError === null ? null : Str::limit($providerError, 300, '');

        $state->forceFill([
            'status' => ProviderState::STATUS_FAILED,
            'consecutive_failures' => (int) $state->consecutive_failures + 1,
            'last_failure_at' => now(),
            'last_error' => Str::limit($message.' '.($providerError ?? ''), 500, ''),
            'circuit_open_until' => now()->addSeconds(self::recheckSeconds()),
            'meta_json' => $meta,
        ])->save();

        Log::error('Zugang zum Provider ist nicht benutzbar.', [
            'provider' => $provider,
            'reason' => $reason,
            'provider_error' => $providerError,
        ]);

        // Ein Alarm fuer alle Portale (tenant_id null): bei 20 Portalen
        // stuenden sonst 20 gleichlautende Baender in der Uebersicht.
        ContentAlert::raise(
            self::alertKey($provider),
            $message,
            null,
            ContentAlert::LEVEL_CRITICAL,
            [
                'provider' => $provider,
                'reason' => $reason,
                'provider_error' => $providerError === null ? null : Str::limit($providerError, 300, ''),
            ],
        );

        return new ProviderAccountException($message, $provider, $reason, $providerError);
    }

    /**
     * Ein geglueckter Aufruf beendet den Zustand — mehr braucht es nicht,
     * der naechste Aufruf nach der Aufladung ist der Beleg.
     */
    public static function reportSuccess(string $provider): void
    {
        $state = ProviderState::query()->where('provider', $provider)->first();

        if ($state === null || $state->status !== ProviderState::STATUS_FAILED) {
            return;
        }

        $meta = is_array($state->meta_json) ? $state->meta_json : [];
        unset($meta['account_reason'], $meta['account_message'], $meta['account_failed_at'], $meta['account_error']);

        $state->forceFill([
            'status' => ProviderState::STATUS_OK,
            'consecutive_failures' => 0,
            'last_success_at' => now(),
            'last_error' => null,
            'circuit_open_until' => null,
            'meta_json' => $meta,
        ])->save();

        ContentAlert::settle(self::alertKey($provider));
    }

    /**
     * Faellt sofort durch, solange der Zugang als tot gilt — ohne HTTP-Aufruf
     * und ohne Kosten. Nach Ablauf der Frist laesst der naechste Aufruf einen
     * Probeversuch durch.
     *
     * @throws ProviderAccountException
     */
    public static function assertUsable(string $provider): void
    {
        $state = ProviderState::query()->where('provider', $provider)->first();

        if ($state === null || $state->status !== ProviderState::STATUS_FAILED) {
            return;
        }

        if ($state->circuit_open_until === null || $state->circuit_open_until->isPast()) {
            return;
        }

        $meta = is_array($state->meta_json) ? $state->meta_json : [];
        $reason = is_string($meta['account_reason'] ?? null)
            ? $meta['account_reason']
            : ProviderAccountException::REASON_CREDIT;

        throw new ProviderAccountException(
            is_string($meta['account_message'] ?? null) ? $meta['account_message'] : self::message($reason, $provider),
            $provider,
            $reason,
        );
    }

    private static function alertKey(string $provider): string
    {
        return ContentAlert::KEY_PROVIDER_ACCOUNT.':'.$provider;
    }

    private static function recheckSeconds(): int
    {
        return max(60, (int) config('content.resilience.account_recheck_seconds', 3600));
    }
}
