# Re Notifier

Re Notifier watches SS.lv feeds and notifies when feed items satisfy configured watch criteria.

## Language

**WatchProfile**:
One category, one matching intent, and one or more source URLs. A WatchProfile is a business
intent, not just a notification label; if one Listing is relevant to two WatchProfiles, it has two
separate relevance histories. A WatchProfile has one stable human-readable id that is part of
notification history and is also used to derive the notification hashtag; changing it requires an
explicit history migration. Source-specific constraints already encoded upstream, such as SS.lv
location paths or Banknote inventory category files, belong in WatchProfile source URLs.
_Avoid_: SearchProfile, profile when the meaning is ambiguous

**Category**:
A family of SS.lv feed items that share the same field language and matching shape, such as
apartments, houses, laptops, or headphones. A WatchProfile has exactly one Category; apartment and
house Categories are both real estate.
_Avoid_: Type, section

**Property**:
One physical apartment or house. The same Property may be published as multiple Listings over time,
including Listings with different SS.lv URLs.
_Avoid_: Listing when referring to the physical real-estate object

**Listing**:
One publication occurrence exposed by a source. Its URL is a locator, not permanent identity: SS.lv
may reuse the URL for a later Listing of a different Property. A Listing can be relevant to zero,
one, or many WatchProfiles.
_Avoid_: Property, URL when referring to the publication, Ad, item when the meaning is ambiguous

**ListingRevision**:
One observed content snapshot of a Listing for one WatchProfile. A changed price or description may
produce another ListingRevision. A URL containing incompatible Property fields begins a different
Listing rather than another revision of the original Listing. A seen ListingRevision is skipped
for that WatchProfile; a changed ListingRevision may notify again, even when the SS.lv URL is
unchanged.
_Avoid_: Duplicate, seen ad

## Example Dialogue

Dev: "Does this Babite house belong to the real estate Category?"

Domain expert: "Yes. It may match the family-house WatchProfile, but not the renovation WatchProfile."

Dev: "If the same SS.lv item matches two WatchProfiles, is it one notification or two?"

Domain expert: "Two. Relevance belongs to the WatchProfile, not only to the SS.lv item."

Dev: "The same SS.lv URL changed price but still describes Valdemāra 39. New Listing?"

Domain expert: "No. Same Listing, new ListingRevision."

Dev: "The same URL now describes Čaka 133 with different rooms and area. New Listing?"

Domain expert: "Yes. SS.lv reused the locator for a different Property."

Dev: "If the same URL has changed content and still matches, should it notify again?"

Domain expert: "Yes. Treat it as a new ListingRevision for that WatchProfile."

Dev: "Can I rename the WatchProfile id to change the notification hashtag?"

Domain expert: "No. The id belongs to notification history. Migrate history explicitly if the id
must change."

Dev: "Should Babite be a house matching criterion?"

Domain expert: "No, if SS.lv already exposes it as a separate RSS feed path. Put that constraint in
the WatchProfile feed URL."

Dev: "Can headphone model matching rely only on the structured Marka field?"

Domain expert: "No. Sellers write models in title and description text with spaces, punctuation,
and casing drift, so match against normalized seller-written text."
