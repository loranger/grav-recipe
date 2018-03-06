<?php

namespace Deployer;

require 'recipe/composer.php';

/*
|--------------------------------------------------------------------------
| Server and services
|--------------------------------------------------------------------------
 */

$hostname = 'website.com';
$path = '/path/to/project';

host('production')
    ->hostname($hostname)
    ->user(exec('whoami'))
    ->forwardAgent()
    ->set('deploy_path', $path . '/prod')
    ->set('http_user', 'www-data')
    ->set('writable_mode', 'chown')
    ->set('writable_use_sudo', true);

host('stage')
    ->hostname($hostname)
    ->user(exec('whoami'))
    ->forwardAgent()
    ->set('deploy_path', $path . '/stage')
    ->set('http_user', 'www-data')
    ->set('writable_mode', 'chown')
    ->set('writable_use_sudo', true);

set('ssh_type', 'native');
set('ssh_multiplexing', true);

set('repository', `git config --get remote.origin.url`);

set('shared_dirs', [
    'assets',
    'images',
    'user/data',
    'user/accounts',
    'user/pages',
    'logs',
    'backup',
]);

set('writable_dirs', [
    'assets',
    'backup',
    'cache',
    'logs',
    'images',
    'user/data',
    'user/accounts',
    'user/pages',
    'tmp',
]);

/*
|--------------------------------------------------------------------------
| Tasks
|--------------------------------------------------------------------------
 */

task('fast', [
    'deploy:release',
    'deploy:update_code',
    'deploy:symlink',
])->desc('Fast deployment (without prepare or composer update)');

task('fix:permissions', function () {
    cd('{{deploy_path}}');
    run('sudo chown -R `whoami`:www-data .');
    run('sudo chmod -R g+rwX .');
})->desc('Apply right permissions on deployments folder');

task('pages:upload', function () {
    upload('user/pages/', '{{deploy_path}}/shared/user/pages');
    run('sudo chown -R `whoami`:www-data {{deploy_path}}/shared/user/pages');
    run('sudo chmod -R g+rwX {{deploy_path}}/shared/user/pages');
})->desc('Upload local pages');

task('pages:download', function () {
    $options = [];
    if (askConfirmation(sprintf('Do you want to remove local pages that does not exists on %s?', get('hostname')))) {
        $options = ['--delete'];
    }
    download('{{deploy_path}}/shared/user/pages/', 'user/pages', ['options' => $options]);
})->desc('Download remote pages');

task('accounts:upload', function () {
    upload('user/accounts/', '{{deploy_path}}/shared/user/accounts');
    run('sudo chown -R `whoami`:www-data {{deploy_path}}/shared/user/accounts');
    run('sudo chmod -R g+rwX {{deploy_path}}/shared/user/accounts');
})->desc('Upload local accounts');

task('accounts:download', function () {
    $options = [];
    if (askConfirmation(sprintf('Do you want to remove local accounts that does not exists on %s?', get('hostname')))) {
        $options = ['--delete'];
    }
    download('{{deploy_path}}/shared/user/accounts/', 'user/accounts', ['options' => $options]);
})->desc('Download remote accounts');

task('grav:clear-cache', function () {
    cd('{{deploy_path}}/current');
    run('sudo -u www-data bin/grav clear-cache');
})->desc('Clear Grav caches');

task('grav:backup', function () {
    cd('{{deploy_path}}/current');
    $backup = run('sudo -u www-data bin/grav backup');
    writeln($backup);
})->desc('Backup Grav installations');

task('grav:upgrade-core', function () {
    cd('{{deploy_path}}/current');
    $self_upgrade = run('sudo -u www-data bin/gpm self-upgrade -y');
    writeln($self_upgrade);
})->desc('Upgrade Grav Core');

task('grav:upgrade-plugins', function () {
    cd('{{deploy_path}}/current');
    $upgrade = run('sudo -u www-data bin/gpm update -y');
    writeln($upgrade);
})->desc('Upgrade Grav Plugins');

task('grav:upgrade', [
    'grav:backup',
    'grav:upgrade-core',
    'grav:upgrade-plugins'
])->desc('Upgrade Grav install');

task('reload:php-fpm', function () {
    run('sudo /usr/sbin/service php5-fpm reload');
})->desc('Reload PHP5 FPM configuration');

task('reload:nginx', function () {
    run('sudo /usr/sbin/service nginx reload');
})->desc('Reload Nginx configuration');

/*
|--------------------------------------------------------------------------
| Hooks
|--------------------------------------------------------------------------
 */

task('deploy', [
    'deploy:prepare',
    'deploy:lock',
    'deploy:release',
    'deploy:update_code',
    'deploy:shared',
    'deploy:vendors',
    'deploy:writable',
    'deploy:symlink',
    'deploy:unlock',
    'grav:clear-cache',
    'cleanup',
])->desc('Deploy your project');
after('deploy', 'success');

after('deploy:writable', 'fix:permissions');
// after('deploy:shared', 'fix:storage');
