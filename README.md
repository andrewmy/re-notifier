# Real Estate ad notifier

Fetches RE ads from SS.lv and notifies about matches to the chosen Telegram chat.

## Usage

1. Create a Telegram bot
2. In `.env.local`, set the `TG_URI` env variable to `https://api.telegram.org/bot<X>:<Y>/sendMessage?chat_id=<Z>`, where `bot<X>:<Y>` is the API token and `<Z>` is the chat ID. [How to get the chat ID](https://sean-bradley.medium.com/get-telegram-chat-id-80b575520659).
3. `composer install`
4. `./app.php update`

Works best from cron.

## Deployment

This repository is set up to deploy after every push to `main` on GitHub (GH) through Deployer. To replicate this experience:

1. Generate SSH key #1 on the server.
2. Add the public key #1 to the GH profile — this will allow Deployer to fetch code from the repository.
3. Generate SSH key #2 anywhere.
4. Add the public key #2 to `~/.ssh/authorized_keys` on the server.
5. Add the private key #2 to the GH repository secrets as `PRIVATE_KEY` — this will allow Deployer to log into the server and do its thing.
6. Update the repository, host, user, and path in `deploy.php`
