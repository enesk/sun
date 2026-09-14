<?php

namespace Deployer;

require 'recipe/laravel.php';
require 'contrib/crontab.php';

// Configs  (You need to configure these)
//
// Produktionsziel (#106, Stand 09.09.2026). Der Server traegt bereits eine
// laufende Installation dieses Repositories unter
// /home/sanitaerfinden/htdocs/sanitaerfinden.dev — sie ist von Hand
// eingerichtet (CloudPanel, nginx, PHP-FPM 8.4) und **nicht** von Deployer
// verwaltet. Deshalb gilt:
//
//   * Die Provisionierungs-Tasks (`dep provision`, provision:supervisor,
//     provision:php-extra, provision:fix-aws-ssh) duerfen auf diesem Server
//     NICHT laufen: er hostet weitere fremde Projekte (widimedia.com,
//     kasernencheck.de, pickyourpic.de, ...), die eine Neuprovisionierung
//     von nginx/PHP mitnehmen wuerde.
//   * `dep deploy` legt eine release-basierte Struktur unter $deployPath an.
//     Solange der vhost sanitaerfinden.dev.conf noch auf das handgepflegte
//     Verzeichnis zeigt, aendert ein Deploy nichts an der ausgelieferten
//     Seite — der Wechsel des vhost-Roots auf {{deploy_path}}/current/public
//     ist ein bewusster Handgriff.
//
// Ablauf und Proben: docs/messungen/produktionsumgebung-anleitung.md

$remoteUser = 'sanitaerfinden';   // the user that will be used to connect to remote server and deploy the app
$sudoPassword = '';  // the sudo password of the remote user (leave empty if using ssh key)

// Ziel eines Deployer-Deploys. Absolut, ohne Tilde: die Tasks unten setzen den
// Wert unveraendert in Supervisor-Confs ein, und '~' wird dort nicht aufgeloest
// (#117). Achtung: die AUSGELIEFERTE Installation liegt nicht hier, sondern
// handgepflegt unter /home/sanitaerfinden/htdocs/sanitaerfinden.dev. Ein
// Horizon-Programm, das auf {{deployPath}}/current/artisan zeigt, laeuft dort
// ins Leere — genau daran ist 'sanitaerfinden-worker' mit FATAL gescheitert.
// Das laufende Horizon-Programm auf dem Server heisst
// 'sanitaerfinden-horizon', zeigt auf den echten Pfad und wird von Hand
// gepflegt (docs/messungen/produktionsumgebung-anleitung.md, Abschnitt 5.2).
$deployPath = '/home/sanitaerfinden/app';

$host = '88.198.64.145';    // the host of the remote server (can be an IP or domain)
$domain = 'sanitaerfinden.com';   // the domain of the app

$repository = 'git@github.com:enesk/sun.git';      // has to be in the SSH format
$subDirectory = '';    // the subdirectory of the repository where the app is located (this is the directory that contains the composer.json file). Leave empty if the app is in the root of the repository (by default)

$phpVersion = '8.4'; // the version of PHP to be installed on the server

// End of configs
// ///////////////////////////////////
// ///////////////////////////////////

set('repository', $repository);
set('sub_directory', $subDirectory);

set('nodejs_version', 'node_22.x');

add('shared_files', []);
add('shared_dirs', []);
add('writable_dirs', []);

host($host)
    ->set('remote_user', $remoteUser)
    ->set('deploy_path', $deployPath)
    ->set('sudo_password', $sudoPassword)
    ->set('domain', $domain)
    ->set('public_path', 'public')
    ->set('php_version', $phpVersion);

desc('Install & build npm packages');
task('npm:build', function () {
    run('cd {{release_path}} && npm ci && npm run build');
});

desc('Provision extra PHP packages');
task('provision:php-extra', function () {
    $version = get('php_version');
    info("Installing Extra PHP $version packages");
    $packages = [
        "php$version-redis",
    ];

    run('apt-get install -y '.implode(' ', $packages), ['env' => ['DEBIAN_FRONTEND' => 'noninteractive']]);
})->verbose()
    ->limit(1);

desc('Provision supervisor');
task('provision:supervisor', function () use ($remoteUser, $deployPath) {
    info('Installing Supervisor');

    run('apt-get install -y supervisor', ['env' => ['DEBIAN_FRONTEND' => 'noninteractive']]);

    $supervisorConfig = <<<'EOF'
[program:horizon]
process_name=%(program_name)s
command=php {{deployPath}}/current/artisan horizon
autostart=true
autorestart=true
user={{user}}
redirect_stderr=true
stdout_logfile={{deployPath}}/log/horizon.log
stopwaitsecs=60
EOF;

    $deployPathRelativeToDeployerUser = str_replace('~', '/home/'.$remoteUser, $deployPath);

    $supervisorConfig = str_replace('{{deployPath}}', $deployPathRelativeToDeployerUser, $supervisorConfig);
    $supervisorConfig = str_replace('{{user}}', $remoteUser, $supervisorConfig);

    $supervisorConfigPath = '/etc/supervisor/conf.d/horizon.conf';

    run("echo '$supervisorConfig' > $supervisorConfigPath");

    run('supervisorctl reread');
    run('supervisorctl update');
    run('supervisorctl start horizon');
})->verbose()
    ->limit(1);

desc('Fixes a common bug with AWS EC2 instances that causes SSH to fail');
task('provision:fix-aws-ssh', function () {
    $authorizedKeys = run('cat /home/deployer/.ssh/authorized_keys');

    $searchSting = 'no-port-forwarding,no-agent-forwarding,no-X11-forwarding,command="echo \'Please login as the user \"ubuntu\" rather than the user \"root\".\';echo;sleep 10;exit 142" ';

    if (str_contains($authorizedKeys, $searchSting)) {
        $authorizedKeys = str_replace($searchSting, '', $authorizedKeys);
        $authorizedKeys = trim($authorizedKeys);
        run('echo "$KEY" > /home/deployer/.ssh/authorized_keys', ['env' => ['KEY' => $authorizedKeys]]);
    }
})->verbose()
    ->limit(1);

desc('Install supervisor programs for the content pipeline queues');
task('deploy:supervisor-content', function () use ($remoteUser, $deployPath) {
    // Nur fuer Server, die die Queues ohne Horizon fahren. Laeuft Horizon,
    // stehen dieselben Worker-Zahlen in config/horizon.php und dieser Task
    // wird nicht aufgerufen — sonst zieht jede Queue zwei Konsumenten.
    $deployPathAbsolute = str_replace('~', '/home/'.$remoteUser, $deployPath);

    foreach (['content-sources', 'content-generate', 'content-publish'] as $program) {
        $config = file_get_contents(__DIR__.'/deploy/supervisor/'.$program.'.conf');
        $config = str_replace(['{{deploy_path}}', '{{user}}'], [$deployPathAbsolute, $remoteUser], $config);

        run("cat > /etc/supervisor/conf.d/$program.conf <<'EOF'\n$config\nEOF");
    }

    run('supervisorctl reread');
    run('supervisorctl update');
})->verbose()
    ->limit(1);

desc('Generate sitemap');
task('deploy:sitemap', artisan('app:generate-sitemap', ['skipIfNoEnv']));

desc('Export configs from database to cache');
task('deploy:export-configs', artisan('app:export-configs', ['skipIfNoEnv']));

// seed database
after('artisan:migrate', 'artisan:db:seed');

// npm
after('artisan:migrate', 'npm:build');

// php
after('provision:php', 'provision:php-extra');
after('provision:verify', 'provision:supervisor');
after('provision:deployer', 'provision:fix-aws-ssh');

after('deploy:success', 'artisan:horizon:terminate'); // to restart horizon after deploy
after('deploy:success', 'artisan:queue:restart'); // Worker der Content-Queues auf den neuen Release ziehen (#22)
after('deploy:success', 'crontab:sync');
after('deploy:success', 'deploy:sitemap');
after('deploy:success', 'deploy:export-configs');

after('deploy:failed', 'deploy:unlock');

add('crontab:jobs', [
    '* * * * * cd {{current_path}} && {{bin/php}} artisan schedule:run >> /dev/null 2>&1',
]);
