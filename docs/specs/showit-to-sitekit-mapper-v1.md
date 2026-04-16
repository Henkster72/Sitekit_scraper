# Showit To SiteKit Mapper v1

## Purpose

This mapper is an intermediate planning contract for translating Showit templates
into canonical SiteKit JSON or `.skit` exports.

It is not the final SiteKit payload.

Use it to answer four questions for every Showit section:

1. What is this section trying to do?
2. Which Showit pattern family does it belong to?
3. Which SiteKit block + variant is the closest structural match?
4. Which copy and image URLs belong in that target block?

## Section Shape

Every mapped section uses the same outer shape:

```json
{
  "id": "home-hero",
  "kind": "section",
  "role": "hero",
  "sourcePattern": {
    "family": "editorial-hero-overlay",
    "label": "Editorial Hero Overlay",
    "confidence": 0.96,
    "cues": ["full-bleed photo", "stacked headline", "single CTA"]
  },
  "targetBlock": {
    "type": "hero",
    "variant": 5,
    "layoutMode": "title-overlay"
  },
  "content": {
    "heading": "the NEW ROMANTICS",
    "subheading": "Acclaimed international wedding film photography for the new romantics.",
    "ctaLabel": "Explore the Work",
    "ctaUrl": "/portfolio"
  },
  "media": [
    {
      "slot": "primary",
      "url": "https://static.showit.co/1200/RRr87m7tT3qx49LSSfitsg/51489/lecollectif-34_cropped.jpg",
      "alt": "Editorial wedding portrait",
      "source": "showit"
    }
  ],
  "style": {
    "backgroundTone": "dark-editorial",
    "copyAlignment": "overlay-left"
  },
  "notes": [
    "Prefer the Showit image URL directly.",
    "Do not invent extra bullets or gallery items."
  ]
}
```

## Pattern Families

Use these families as the first-pass Showit classifier:

- `editorial-hero-overlay` -> `hero` variant `5` or `3` with `layoutMode: title-overlay`
- `split-intro-story` -> `content` variant `1` or `8`
- `oversized-services-list` -> `services` variant `1`
- `proof-logo-or-monogram` -> `banner`, `truststrip`, or `content` depending on density
- `bio-band-with-portrait` -> `content` variant `1`
- `dark-cta-band` -> `cta` variant `6` or `1`
- `portfolio-feature-list` -> `gallery`, `cards`, or `carousel` based on whether items are images-first or text-first
- `instagram-grid` -> `gallery` variant `8` with `layoutMode: social-grid`
- `newsletter-split` -> `cta` variant `6` with `layoutMode: newsletter-split`
- `editorial-footer-nav` -> `footer` variant matching brand density, keep links and credits simple

## Showit Rules

- Treat each Showit canvas section as one mapper `section`.
- Use the original Showit image URLs directly when they are public and stable.
- Keep `site.baseUrl` at the final SiteKit payload level when most URLs share one origin.
- Prefer the live site for real copy, links, and image URLs.
- Use DivMagic HTML only as a structural aid when the live DOM makes section boundaries unclear.
- Do not export SiteKit preset-set shapes such as `siteTypes`, `sets`, or `defaultSiteTypeId` when the goal is a SiteKit page/project payload.

## DivMagic Guidance

DivMagic is helpful when:

- Showit DOM is deeply nested or highly absolute-positioned
- you need quick section boundaries or image URL discovery
- the live page is harder to inspect than the copied HTML

DivMagic is not required.

The priority order is:

1. live Showit URL
2. DivMagic HTML as a fallback or cross-check
3. screenshots for layout confirmation

## Final Output Rule

Build the mapper mentally or explicitly first, then emit the canonical SiteKit
payload that follows:

- [skit-spec-v1.md](/home/henk/Documents/php_websites/SiteKit/docs/specs/skit-spec-v1.md)
- [skit-spec-v1.schema.json](/home/henk/Documents/php_websites/SiteKit/docs/specs/skit-spec-v1.schema.json)

Do not include mapper-only keys in the final SiteKit payload unless the caller
explicitly asks for the mapper itself.
