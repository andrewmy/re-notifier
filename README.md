# Classified ad listing notifier

Fetches listings from SS.lv and notifies about matches to the chosen Telegram chat.

## Usage

1. Create a Telegram bot
2. In `.env.local`, set the `TG_URI` env variable to
   `https://api.telegram.org/bot<X>:<Y>/sendMessage?chat_id=<Z>`,
   where `bot<X>:<Y>` is the API token and `<Z>` is the chat ID.
   [How to get the chat ID](https://sean-bradley.medium.com/get-telegram-chat-id-80b575520659).
3. `composer install`
4. Copy `config/watch_profiles.example.php` to `config/watch_profiles.local.php` and adjust criteria.
   The example file is documentation only; production hard-loads the local file and fails if missing.
5. `./app.php update --dry-run` to test without sending notifications
6. `./app.php update` to run the real notifier

Works best from cron.

## Watch Profiles

Each WatchProfile defines one SS.lv feed URL, a category (apartment or house), and matching criteria.

`config/watch_profiles.example.php` demonstrates the structure with safe sample profiles.
Do not edit it for real use — create `config/watch_profiles.local.php` instead.
This file is gitignored and never committed.

Same listing can match multiple WatchProfiles, producing separate notifications per profile.

## Commands

- `./app.php update --dry-run` — fetch, parse, match, log; no Telegram, no save
- `./app.php update` — fetch, parse, match, notify via Telegram, save seen revisions
- `./app.php listing:raw <url>` — show all stored RSS descriptions for a listing URL

## Deployment

This repository is set up to deploy after every push to `main` on GitHub (GH) through Deployer.
To replicate this experience:

1. Generate SSH key #1 on the server.
2. Add the public key #1 to the GH profile — this will allow Deployer to
   fetch code from the repository.
3. Generate SSH key #2 anywhere.
4. Add the public key #2 to `~/.ssh/authorized_keys` on the server.
5. Add the private key #2 to the GH repository secrets as `PRIVATE_KEY` —
   this will allow Deployer to log into the server and do its thing.
6. Update the repository, host, user, and path in `deploy.php`

## Docker Deployment

The Docker image is automatically built and pushed to `ghcr.io/andrewmy/re-notifier:latest`
on every push to `main`. It supports both amd64 and arm64 architectures.

The container runs a cron job every 5 minutes to check for new ads, the schedule is editable in the
`crontab` file.

### Without Deployer

1. Copy `docker-compose.yml` and `crontab` to your server
2. Create a `.env` file with the required variables, see `.env.dist`
3. Create a `var/` directory for the SQLite database
4. Copy `config/watch_profiles.example.php` to `config/watch_profiles.local.php` and adjust
5. Start the container:

   ```shell
   docker compose pull
   docker compose up -d
   ```

### With Deployer

1. Configure your host in `deploy.php`:

   ```php
   host('my-server')
       ->set('hostname', 'server.example.com')
       ->set('remote_user', 'username')
       ->set('deploy_path', '/path/to/re-notifier');
   ```

2. Run the deployment:

    ```shell
    just docker-build
    just deploy-docker
    ```

This will:

- Sync `docker-compose.yml` to the server
- Upload local files if they don't exist on remote:
  `.env.local` => `.env`, `var/db.sqlite`,
  `config/watch_profiles.local.php`, `crontab`
- Pull the latest image and restart the container
