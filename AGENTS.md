# AGENTS.md

@/Users/andr/.codex/RTK.md

## Fast Path

PHP 8.5 Symfony Console app. Fetches SS.lv RSS ads per WatchProfile, parses HTML descriptions using category-specific parsers, filters matches, stores seen revisions in SQLite, enriches real estate matches with TirgusDati history, posts to Telegram.

Main flow:

`app.php` -> `src/Ui/Cli/Update.php` -> per-WatchProfile RSS fetch -> `SsLvParser::parse()` -> `WatchProfile::matches()` -> `DbalListingRepository`

Domain language and business invariants live in `docs/domain-glossary.md`; read it before changing WatchProfile, Category, Listing, ListingRevision, matching, duplicate detection, profile ids, or notification history.

## Commands

- Install: `composer install`
- Run safely: `./app.php update --dry-run`
- Run real notifier: `./app.php update`
- Query raw listings: `./app.php listing:raw <url>`
- Full local check: `just ci`
- Static analysis only: `just stan`
- Architecture check: `just deptrac`
- List targets: `just --list`
- Docker deploy: `just docker-build` then `just deploy-docker`

`just ci` runs `phpcbf`, so it can modify files.

## Guardrails

- Do not print `.env.local`; it contains Telegram config.
- Do not print `config/watch_profiles.local.php`; it contains watch intent.
- Prefer `--dry-run` unless explicitly testing Telegram delivery.
- `update` does network I/O to SS.lv and TirgusDati even with `--dry-run`.
- `--dry-run` does not save revisions and does not send Telegram.
- Real mode sends Telegram first, then saves revision (send failures retry next run).
- Validation is Composer validate, dependency analyser, PHPStan, Deptrac, PHPCBF, Composer audit.
- SQLite schema migrates in `DbalListingRepository` constructor; no external migration tool.

## Architecture

- `src/Domain/` - `Listing` interface, `ApartmentListing`, `HouseListing`, `Category` enum, `WatchProfile`, `Criteria` variants, `ListingRepository` interface.
- `src/Application/` - `TelegramNotifier`, `ListingEnricher` interface, `TirgusDatiPriceHistoryEnricher`, `Notifier` interface, `EnrichmentData`.
- `src/Infrastructure/SsLv/` - `SsLvFieldExtractor`, `SsLvRssItem`, `SsLvParser` interface, `ApartmentParser`, `HouseParser`.
- `src/Infrastructure/` - `DbalListingRepository` SQLite implementation with schema migration, `WatchProfileLoader` config loader.
- `src/Ui/Cli/` - `Update` command, `ListingRaw` command.

Data flow:

Per WatchProfile: RSS fetch -> XML parse -> per-item: category parse -> match criteria -> duplicate check by (watch_profile_id, url, content_hash) -> Telegram notification with hashtag -> save revision -> optional TirgusDati enrichment in notification.

Config: `config/watch_profiles.local.php` returns array of WatchProfile instances. `config/watch_profiles.example.php` is committed documentation. Runtime fails loudly if local config is missing.

## Risky Areas

- SS.lv parsing is regex-based in `SsLvFieldExtractor`; feed markup or language changes can break rooms, space, price, or street extraction.
- Match criteria live in per-category Criteria classes: `ApartmentCriteria`, `HouseCriteria`.
- House land area in hectares is normalized to square meters in `HouseParser`.
- TirgusDati integration relies on unauthenticated token bootstrap from `/api/user/me`, then bearer auth for history search.
- Docker deploy target is Deployer host `reservoir`.
- Content hash uses md5 of single-line RSS description; URL is not permanent identity.
