<?php

declare(strict_types=1);

namespace Tests\Unit\DbPull;

use App\Services\DbPull\TenantStorageSync;
use Illuminate\Support\Facades\Process;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

/**
 * Der Schutz des rsync-Ziels (#9, #10).
 *
 * `rsync --delete` loescht lokal alles, was in der Quelle fehlt. Zeigt das
 * Ziel woandershin als auf `storage/tenant<id>/app/public`, loescht der Lauf
 * fremde Verzeichnisse. Deshalb ist das hier der ausfuehrlichste Test des
 * Vorhabens — und rsync selbst wird gefakt, es laeuft in keinem Fall.
 */
class TenantStorageSyncGuardTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Kein Test darf einen echten rsync starten, auch nicht versehentlich
        // ueber einen Pfad, den dieser Test gar nicht im Blick hat.
        Process::fake(fn () => Process::result("Number of files: 0\n", '', 0));
    }

    /** @return array<string, array{0: string}> */
    public static function boesePortalIds(): array
    {
        return [
            'Schraegstrich' => ['19/../../etc'],
            'nur Punkte' => ['..'],
            'Punkte im Namen' => ['tenant..19'],
            'absoluter Pfad' => ['/etc/passwd'],
            'Leerzeichen und Semikolon' => ['19; rm -rf /'],
            'Tilde' => ['~'],
            'leer' => [''],
        ];
    }

    #[DataProvider('boesePortalIds')]
    public function test_portal_id_die_aus_dem_storage_herausfuehrt_wird_abgewiesen(string $tenantId): void
    {
        $sync = $this->sync();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unerwartete Portal-ID fuer rsync');

        $sync->pull($tenantId, dryRun: true);
    }

    public function test_eine_echte_uuid_und_eine_zahl_sind_erlaubt(): void
    {
        $sync = $this->sync();

        foreach (['19', '74a732dc-d630-4c1b-8cb3-d98e5c76709a'] as $tenantId) {
            $result = $sync->pull($tenantId, dryRun: true);

            $this->assertTrue($result['ok']);
            $this->assertSame(
                storage_path('tenant'.$tenantId.'/app/public'),
                $result['target'],
                'Das Ziel muss genau der Tenant-Storage dieses Portals sein.',
            );
        }
    }

    public function test_ein_zielpfad_ausserhalb_des_tenant_storage_wird_abgewiesen(): void
    {
        // Ein Praefix mit .. ist der Weg, auf dem ein falsch gesetztes
        // tenant_storage_prefix das Ziel aus storage/ herausziehen wuerde.
        $sync = $this->sync(storagePrefix: '../ausserhalb-tenant');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unerwarteter Zielpfad fuer rsync --delete');

        $sync->pull('19', dryRun: true);
    }

    public function test_storage_path_der_aus_dem_baum_fuehrt_wird_schon_beim_normieren_abgewiesen(): void
    {
        foreach (['../../etc', 'media/../../..', 'media/logos;rm -rf /', '..', 'media/$(whoami)'] as $boese) {
            try {
                TenantStorageSync::normalizeSubPath($boese);
                $this->fail("Teilpfad {$boese} haette abgewiesen werden muessen.");
            } catch (RuntimeException $exception) {
                $this->assertStringContainsString('Unerwarteter Teilpfad', $exception->getMessage());
            }
        }
    }

    public function test_harmlose_teilpfade_werden_normiert(): void
    {
        $this->assertSame('', TenantStorageSync::normalizeSubPath(''));
        $this->assertSame('', TenantStorageSync::normalizeSubPath('.'));
        $this->assertSame('media/logos', TenantStorageSync::normalizeSubPath('/media/logos/'));
        $this->assertSame('media/logos', TenantStorageSync::normalizeSubPath('media//logos'));
    }

    /**
     * Ein absolut geschriebener Teilpfad wird als relativer gedeutet, nicht
     * abgewiesen — der fuehrende Schraegstrich faellt weg. Das ist gewollt
     * (`--storage-path=/media/logos` soll tun, was der Aufrufer meint), und
     * es bleibt harmlos, weil der Guard danach gegen den erwarteten Pfad
     * prueft: aus `/etc/passwd` wird ein Unterordner des Tenant-Storage,
     * nie das echte /etc.
     */
    public function test_ein_absolut_geschriebener_teilpfad_bleibt_im_tenant_storage(): void
    {
        $this->assertSame('etc/passwd', TenantStorageSync::normalizeSubPath('/etc/passwd'));

        $result = $this->sync(subPath: 'etc/passwd')->pull('19', dryRun: true);

        $this->assertSame(storage_path('tenant19/app/public/etc/passwd'), $result['target']);
        $this->assertStringStartsWith(storage_path('tenant'), $result['target']);
    }

    public function test_mit_teilpfad_zeigt_delete_nur_in_den_unterbaum(): void
    {
        $sync = $this->sync(subPath: 'media/logos');

        $result = $sync->pull('19', dryRun: true);

        $this->assertSame(storage_path('tenant19/app/public/media/logos'), $result['target']);

        Process::assertRan(function ($process) {
            $argumente = $process->command;

            $this->assertContains('--delete', $argumente);
            $this->assertContains(storage_path('tenant19/app/public/media/logos').'/', $argumente);

            // Entscheidend: das letzte Argument ist das Ziel, und es endet im
            // Unterbaum — nicht in app/public und schon gar nicht in storage/.
            $ziel = end($argumente);
            $this->assertStringEndsWith('/app/public/media/logos/', $ziel);

            return true;
        });
    }

    public function test_quelle_bleibt_live_und_ziel_bleibt_lokal(): void
    {
        $sync = $this->sync();

        $sync->pull('19', dryRun: true);

        Process::assertRan(function ($process) {
            $argumente = $process->command;
            $ziel = array_pop($argumente);
            $quelle = array_pop($argumente);

            $this->assertStringStartsWith('sun-sync:', $quelle, 'Gelesen wird von Live.');
            $this->assertStringStartsWith(storage_path(), $ziel, 'Geschrieben wird lokal.');
            $this->assertStringNotContainsString('sun-sync:', $ziel, 'Niemals auf die Gegenseite schreiben.');

            return true;
        });
    }

    public function test_eine_falsche_groessenangabe_wird_abgewiesen(): void
    {
        foreach (['zwei MB', '2 M', '2MBit', '-2M', '2M;ls'] as $boese) {
            try {
                TenantStorageSync::normalizeMaxSize($boese);
                $this->fail("Groesse {$boese} haette abgewiesen werden muessen.");
            } catch (RuntimeException $exception) {
                $this->assertStringContainsString('Unerwartete Groesse', $exception->getMessage());
            }
        }

        $this->assertNull(TenantStorageSync::normalizeMaxSize(null));
        $this->assertNull(TenantStorageSync::normalizeMaxSize(''));
        $this->assertSame('2M', TenantStorageSync::normalizeMaxSize('2M'));
        $this->assertSame('1.5G', TenantStorageSync::normalizeMaxSize('1.5G'));
        $this->assertSame('500KiB', TenantStorageSync::normalizeMaxSize('500KiB'));
    }

    public function test_max_size_landet_als_argument_aber_ohne_delete_excluded(): void
    {
        $sync = $this->sync(maxSize: '2M');

        $sync->pull('19', dryRun: true);

        Process::assertRan(function ($process) {
            $this->assertContains('--max-size=2M', $process->command);
            $this->assertNotContains(
                '--delete-excluded',
                $process->command,
                'Zu grosse Dateien sollen lokal liegen bleiben, nicht geloescht werden (#9).',
            );

            return true;
        });
    }

    private function sync(
        string $storagePrefix = 'tenant',
        string $subPath = '',
        ?string $maxSize = null,
    ): TenantStorageSync {
        return new TenantStorageSync(
            sshTarget: 'sun-sync',
            remoteStoragePath: '/home/sanitaerfinden/htdocs/sanitaerfinden.dev/storage',
            storagePrefix: $storagePrefix,
            bwlimit: '20m',
            timeout: 60,
            exclude: ['*.log'],
            sshPort: null,
            sshKeyPath: null,
            subPath: $subPath,
            maxSize: $maxSize,
        );
    }
}
