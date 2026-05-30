# Keep schema migrations in the repository

The app uses one SQLite database owned by the listing repository, and the existing schema is already created lazily at startup. We will keep v1 schema changes as idempotent repository-owned migrations instead of adding Doctrine Migrations, because the migration surface is small and adding a migration framework would add configuration, commands, deploy ordering, and operational ceremony before those costs are justified.

Doctrine Migrations can be added later if schema ownership spreads across multiple repositories, migration history or rollback becomes important, or deploys need an explicit migration step separate from app startup.
