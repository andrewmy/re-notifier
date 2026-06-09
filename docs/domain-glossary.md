# Re Notifier

Re Notifier watches SS.lv feeds and notifies when feed items satisfy configured watch criteria.

## Language

**WatchProfile**:
One category, one matching intent, and one or more source URLs. A WatchProfile is a business intent, not just a notification label; if one Listing is relevant to two WatchProfiles, it has two separate relevance histories. A WatchProfile has one stable human-readable id that is part of notification history and is also used to derive the notification hashtag; changing it requires an explicit history migration. Source-specific constraints already encoded upstream, such as SS.lv location paths or Banknote inventory category files, belong in WatchProfile source URLs.
_Avoid_: SearchProfile, profile when the meaning is ambiguous

**Category**:
A family of SS.lv feed items that share the same field language and matching shape, such as apartments, houses, laptops, or headphones. A WatchProfile has exactly one Category; apartment and house Categories are both real estate.
_Avoid_: Type, section

**Listing**:
One item published by SS.lv in a feed. A Listing can be relevant to zero, one, or many WatchProfiles.
_Avoid_: Ad, item when the meaning is ambiguous

**ListingRevision**:
One observed content snapshot of a Listing for one WatchProfile. A seen ListingRevision is skipped for that WatchProfile; a changed ListingRevision may notify again, even when the SS.lv URL is unchanged.
_Avoid_: Duplicate, seen ad

## Example Dialogue

Dev: "Does this Babite house belong to the real estate Category?"

Domain expert: "Yes. It may match the family-house WatchProfile, but not the renovation WatchProfile."

Dev: "If the same SS.lv item matches two WatchProfiles, is it one notification or two?"

Domain expert: "Two. Relevance belongs to the WatchProfile, not only to the SS.lv item."

Dev: "The same SS.lv URL changed price. Is that a new Listing?"

Domain expert: "No. It is the same Listing with changed content, and each matching WatchProfile decides whether that changed content is new enough to notify."

Dev: "If the same URL has changed content and still matches, should it notify again?"

Domain expert: "Yes. Treat it as a new ListingRevision for that WatchProfile."

Dev: "Can I rename the WatchProfile id to change the notification hashtag?"

Domain expert: "No. The id belongs to notification history. Migrate history explicitly if the id must change."

Dev: "Should Babite be a house matching criterion?"

Domain expert: "No, if SS.lv already exposes it as a separate RSS feed path. Put that constraint in the WatchProfile feed URL."

Dev: "Can headphone model matching rely only on the structured Marka field?"

Domain expert: "No. Sellers write models in title and description text with spaces, punctuation, and casing drift, so match against normalized seller-written text."
