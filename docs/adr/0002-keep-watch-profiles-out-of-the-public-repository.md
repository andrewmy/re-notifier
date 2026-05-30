# Keep watch profiles out of the public repository

WatchProfiles describe what locations, categories, and thresholds the notifier watches, which is sensitive user intent even though it is not a credential. We will keep real WatchProfiles in an untracked local PHP config file and commit only a safe example that demonstrates the structure.

The example config is documentation and test fixture material only, not a production fallback. Production must fail loudly when no real WatchProfile config is provided, so the app does not send example notifications and the local repo does not need dirty edits to satisfy runtime requirements.
