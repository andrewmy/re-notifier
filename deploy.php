<?php

declare(strict_types=1);

namespace Deployer;

require 'recipe/composer.php';

// Config

set('repository', 'git@github.com:andrewmy/re-notifier.git');

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

desc('Deploys your project');
task('deploy', [
    'deploy:prepare',
    'deploy:vendors',
    'deploy:publish',
]);
