<?php

declare(strict_types=1);

namespace Deployer;

use function file_exists;

require 'recipe/composer.php';

/**
 * Configuration
 */

// uncomment when deploying to an outside server
// set('repository', 'git@github.com:andrewmy/re-notifier.git');

add('shared_files', ['.env.local']);
add('shared_dirs', ['var']);
add('writable_dirs', ['var']);

/**
 * Hosts
 */

host('your-ssh-host')
    ->set('remote_user', 'your-ssh-user')
    ->set('deploy_path', '~/apps/re-notifier');

host('pi')
    ->set('hostname', 'pi5.local')
    ->set('remote_user', 'andr')
    ->set('deploy_path', '/opt/stacks/re-notifier');

/**
 * Hooks
 */

after('deploy:failed', 'deploy:unlock');

/**
 * SSH deployment
 */

desc('Deploys your project');
task('deploy', [
    'deploy:prepare',
    'deploy:vendors',
    'deploy:publish',
]);

/**
 * Docker deployment
 */
function uploadIfNotExists(
    string $localFile,
    string $remoteFile,
    string $fileDescription,
    string $remoteDir = '',
): void {
    if ($remoteDir) {
        run('mkdir -p {{deploy_path}}/' . $remoteDir);
    }

    if (! file_exists($localFile)) {
        writeln('<comment>Local ' . $fileDescription . ' does not exist, skipping upload</comment>');

        return;
    }

    if (test('[ -f ' . $remoteFile . ' ]')) {
        writeln('<comment>Remote ' . $fileDescription . ' exists, skipping upload</comment>');

        return;
    }

    writeln('<info>Uploading local ' . $fileDescription . '...</info>');
    upload($localFile, $remoteFile);
}

desc('Sync docker-compose.yml to remote');
task('docker:sync-compose', static function (): void {
    run('mkdir -p {{deploy_path}}');
    upload(__DIR__ . '/docker-compose.yml', '{{deploy_path}}/docker-compose.yml');
});

desc('Upload local database if remote does not exist');
task('docker:db-upload', static function (): void {
    uploadIfNotExists(
        __DIR__ . '/var/db.sqlite',
        '{{deploy_path}}/var/db.sqlite',
        'database',
        'var',
    );
});

desc('Upload .env.local if remote .env does not exist');
task('docker:env-upload', static function (): void {
    uploadIfNotExists(
        __DIR__ . '/.env.local',
        '{{deploy_path}}/.env',
        'env file',
    );
});

desc('Upload crontab if remote file does not exist');
task('docker:crontab-upload', static function (): void {
    uploadIfNotExists(
        __DIR__ . '/crontab',
        '{{deploy_path}}/crontab',
        'crontab file',
    );
});

desc('Pull Docker image');
task('docker:pull', static function (): void {
    within('{{deploy_path}}', static function (): void {
        run('docker compose pull');
    });
});

desc('Restart Docker container');
task('docker:up', static function (): void {
    within('{{deploy_path}}', static function (): void {
        run('docker compose up -d');
    });
});

desc('Deploy via Docker');
task('deploy-docker', [
    'docker:sync-compose',
    'docker:db-upload',
    'docker:env-upload',
    'docker:crontab-upload',
    'docker:pull',
    'docker:up',
]);
