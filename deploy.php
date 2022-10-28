<?php

declare(strict_types=1);

namespace Deployer;

require 'recipe/composer.php';

// Config

set('repository', '~/cli-apps/re-notifier/repo');

add('shared_files', ['.env.local']);
add('shared_dirs', ['var']);
add('writable_dirs', ['var']);

// Hosts

host('andr.lv')
    ->set('remote_user', 'andr')
    ->set('deploy_path', '~/cli-apps/re-notifier');

// Hooks

after('deploy:failed', 'deploy:unlock');

// Tasks

task('deploy:update_code', function () {
    $files = array_filter(
        explode("\n", runLocally("ls -1a")),
        fn (string $name) => !in_array($name, ['.', '..', '.git', '.idea', 'var', 'vendor'], true),
    );

    upload(
        $files,
        '{{release_path}}',
        ['progress_bar' => false, 'options' => ['--relative']]
    );
});

desc('Deploys your project');
task('deploy', [
    'deploy:prepare',
    'deploy:vendors',
    'deploy:publish',
]);