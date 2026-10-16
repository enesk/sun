<?php

namespace Tests\Feature;

use App\Analytics\TrackingScriptsConsent;

class AppTest extends FeatureTest
{
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }

    /**
     * Ohne Entscheidung darf der Schnipsel nicht laufen (#28): er liegt inert
     * in einem <template>, aus dem der Browser nichts laedt, und wird von
     * resources/js/consent-scripts.js erst nach der Einwilligung eingesetzt.
     */
    public function test_tracking_scripts_are_held_back_until_consent_is_given()
    {
        config(['app.tracking_scripts' => '<javascript>Google Analytics</javascript>']);

        $response = $this->get('/');

        $response->assertSeeHtml('<template data-consent-script="marketing"><javascript>Google Analytics</javascript></template>');
    }

    public function test_tracking_scripts_are_inserted_directly_once_consent_is_given()
    {
        config(['app.tracking_scripts' => '<javascript>Google Analytics</javascript>']);

        $response = $this->withCookie(TrackingScriptsConsent::cookieName(), '1')->get('/');

        $response->assertSeeHtml('<javascript>Google Analytics</javascript>');
        $response->assertDontSeeHtml('<template data-consent-script=');
    }

    public function test_tracking_scripts_are_left_out_completely_when_consent_is_refused()
    {
        config(['app.tracking_scripts' => '<javascript>Google Analytics</javascript>']);

        $response = $this->withCookie(TrackingScriptsConsent::cookieName(), '0')->get('/');

        $response->assertDontSeeHtml('<javascript>Google Analytics</javascript>');
    }

    /** Die Kategorie entscheidet, auf welches Cookie gewartet wird. */
    public function test_tracking_scripts_can_wait_for_the_statistics_category()
    {
        config([
            'app.tracking_scripts' => '<javascript>Google Analytics</javascript>',
            'app.tracking_scripts_consent' => 'statistics',
        ]);

        $this->assertSame('cookie_consent_statistics', TrackingScriptsConsent::cookieName());

        $response = $this->withCookie('cookie_consent_statistics', '1')->get('/');

        $response->assertSeeHtml('<javascript>Google Analytics</javascript>');
        $response->assertDontSeeHtml('<template data-consent-script=');
    }
}
