# SiteKit Block Variant Guide v1

This guide describes the block variants that already exist in the current
SiteKit builder and renderer.

Use it for scraper or mapper logic that needs to decide:

1. which SiteKit block type to use
2. which variant to pick
3. whether a `layoutMode` is required
4. whether the current builder can already edit the pattern
5. whether a new block variant should be added instead of forcing a weak match

## Decision Rules

### Pattern exists now

Treat a pattern as already supported only when all of these are true:

- the block `type` exists in `app_block_registry()`
- the target `variant` exists in that block's `variants`
- any required `layoutMode` or `navLayout` in this guide exists in the block schema
- the overall composition matches the renderer's structure, not just the color mood

If those conditions hold, the pattern is builder-editable in the current setup.

### Builder-editable now

For this guide, `Builder` means:

- the variant is registered in `inc/blocks.php`
- the block can be created and switched in the current builder
- its existing fields can be edited in the inspector

All variants listed in this guide are available in the current builder.

### Make a new variant when

Add a new variant instead of reusing an existing one when the source pattern
needs a structural behavior the current renderer does not already provide.

Examples:

- a hero needs a fundamentally different text/media composition
- a testimonial section needs a timeline, stack, or interaction not present now
- a gallery needs a new tile topology, not just different crops or colors
- a CTA needs a distinct composition, not just a different palette

Do not make a new variant only for:

- colors
- border styles
- shadows
- type size changes
- swapping left/right when the current block already supports it
- copy density changes that still fit the same composition

## Layout Mode Notes

Some patterns are only "existing patterns" when variant and layout mode are
used together:

- `header`: `navLayout=split-brand-center`, `brand-left-center-links`
- `banner`: `layoutMode=caption-stack`
- `hero`: `layoutMode=title-overlay`
- `truststrip`: `layoutMode=press-bar`
- `gallery`: `layoutMode=editorial-collage`, `category-cards`, `social-grid`
- `testimonials`: `layoutMode=review-stage`, `review-stage-thumbnails`
- `content`: `layoutMode=triptych-link-stack`
- `cta`: `layoutMode=newsletter-split`, `overlay-card`

## Block Variants

### Header

| Variant | Looks Like | Use When | Builder |
| --- | --- | --- | --- |
| 1 | Classic horizontal nav. | Standard brand left, links right, CTA optional. | Yes |
| 2 | Center-weighted nav with softer CTA. | Same base structure as v1, but more balanced and quieter. | Yes |
| 3 | Brand plus tagline emphasis. | Brand voice matters and tagline should sit visibly under brand. | Yes |
| 4 | Centered desktop navigation. | Menu itself is the focus and should read as a centered set. | Yes |
| 5 | Framed pill navigation with elevated link rail. | You want pill-like menu items and a more framed nav surface. | Yes |
| 6 | Centered editorial masthead with split navigation. | Menu should split around the brand; use `navLayout=split-brand-center`. | Yes |
| 7 | Centered masthead with menu and action below the brand. | Brand is centered and visually dominant; split-nav also works well here. | Yes |
| 8 | Compact utility ribbon with dense uppercase navigation. | Tight utility-style header; use `navLayout=brand-left-center-links` for brand-left / links-center behavior. | Yes |

Make a new header variant when the source needs multi-row mega-menu structure, stacked utility bars, or a fundamentally different mobile interaction.

### Banner

| Variant | Looks Like | Use When | Builder |
| --- | --- | --- | --- |
| 1 | Inline badge plus link strip. | Simple announcement bar with badge, copy, and link. | Yes |
| 2 | Highlighted centered announcement. | Promo bar needs more centered emphasis than v1. | Yes |
| 3 | Compact copy-only strip. | Minimal ribbon; use `layoutMode=caption-stack` for stacked caption treatment. | Yes |
| 4 | Dismiss-style utility bar. | Utility or promo bar should feel dismissible. | Yes |
| 5 | Editorial ribbon with expressive type pairing. | Announcement should read more brand/editorial than utility. | Yes |
| 6 | Split ticket rail with brand-style lead. | Ticket-like promo strip with stronger lead chunk. | Yes |
| 7 | Rounded capsule ticker with playful accents. | Marquee or promo strip should feel playful and capsule-like. | Yes |
| 8 | Stacked mini-headline utility strip. | Small headline stack rather than a single linear strip. | Yes |

Make a new banner variant when the source is really a hero-lite section or a multi-card promo rail rather than a true announcement strip.

### Hero

| Variant | Looks Like | Use When | Builder |
| --- | --- | --- | --- |
| 1 | Balanced split with media right. | Default hero with copy on one side and image on the other. | Yes |
| 2 | Larger headline and media-first emphasis. | The image should feel larger and more dominant than v1. | Yes |
| 3 | Centered content over full image background. | Full-bleed background image hero; use `layoutMode=title-overlay` for stronger overlay behavior. | Yes |
| 4 | Media left, text card crossing into image area. | Overlapping text card and image interaction is the core visual cue. | Yes |
| 5 | Full-bleed editorial hero with centered overlay copy. | Cinematic editorial cover-style hero. | Yes |
| 6 | Cinematic background hero with anchored glass copy. | Background image hero with a more anchored glass-card copy treatment. | Yes |
| 7 | Zion-style hero image with centered caption below. | Image-first hero with caption/content sitting below the image plane. | Yes |
| 8 | Editorial split with tall media and stacked actions. | Tall portrait-ish media and layered actions matter. | Yes |

Make a new hero variant when the source needs video-first behavior, overlapping multi-image choreography, or a radically different copy/media stack.

### Divider

| Variant | Looks Like | Use When | Builder |
| --- | --- | --- | --- |
| 1 | Standard solid rule. | Neutral section break. | Yes |
| 2 | Stronger accent divider. | Section break should feel more branded than neutral. | Yes |
| 3 | Badge-led separator. | Divider needs a center label or mini-badge emphasis. | Yes |
| 4 | Subtle spacer or wave feel. | Light atmospheric separation without strong framing. | Yes |
| 5 | Dashed accent rule with tinted badge. | Divider should feel decorative and lightly editorial. | Yes |
| 6 | Ribbon divider with bolder center tag. | Middle tag is the primary signal. | Yes |
| 7 | Dotted cadence with decorative wave dots. | Decorative rhythm matters more than strict separation. | Yes |
| 8 | Bold editorial break with taller wave. | Divider is intentionally loud and acts like a section title marker. | Yes |

Make a new divider variant only when the source needs a distinct structural object, not just a different line treatment.

### Truststrip

| Variant | Looks Like | Use When | Builder |
| --- | --- | --- | --- |
| 1 | Balanced proof grid. | Normal trust logos or names in a static grid. | Yes |
| 2 | Stronger expert ribbon. | Trust section needs more visual emphasis than v1. | Yes |
| 3 | Marquee from right to left. | Logos should auto-scroll in marquee form. | Yes |
| 4 | Marquee from left to right. | Same as v3 but opposite marquee direction. | Yes |
| 5 | Editorial credentials board. | Proof should feel more curated/editorial than utility-grid. | Yes |
| 6 | Expert rail. | Single-row trust rail without marquee. | Yes |
| 7 | Compact expert matrix. | Compact proof board; use `layoutMode=press-bar` for press-bar feel. | Yes |
| 8 | Premium seal board. | More premium framed board of credentials or seals. | Yes |

Make a new truststrip variant when the proof section is actually testimonials, stats, or a mixed media press feature section.

### Features

| Variant | Looks Like | Use When | Builder |
| --- | --- | --- | --- |
| 1 | Tilted deck of cards. | Feature cards should feel loose and layered. | Yes |
| 2 | Dense mosaic with featured lead card. | One lead card should dominate the set. | Yes |
| 3 | Glass editorials with side beams. | Features should feel airy, glassy, and more editorial. | Yes |
| 4 | Folded editorial strips. | Cards should read like folded strips or notes. | Yes |
| 5 | Offset gallery bands. | Feature cards should stagger with gallery-like rhythm. | Yes |
| 6 | Polaroid wall. | Feature images should read like pinned snapshots. | Yes |
| 7 | Dark stage spotlight layout. | Dramatic dark stage with highlighted lead feature. | Yes |
| 8 | Full-width ticket list. | Feature items should read as horizontal ticket rows. | Yes |

Make a new features variant when the source needs tabs, comparison matrices, or nested feature groups instead of a card/list composition.

### Services

| Variant | Looks Like | Use When | Builder |
| --- | --- | --- | --- |
| 1 | Tilted menu cards. | Services are light, card-based, and somewhat playful. | Yes |
| 2 | Horizontal brochure panels. | Services should feel more brochure-like and horizontal. | Yes |
| 3 | Dark premium showcase. | Services need a premium dark presentation. | Yes |
| 4 | Folded paper notes. | Services should read like editorial paper notes. | Yes |
| 5 | Pricing board. | Service list behaves partly like a pricing board. | Yes |
| 6 | Image-forward brochure cards. | Each service needs stronger image presence. | Yes |
| 7 | Sticky-note board. | Informal board-like layout is desired. | Yes |
| 8 | Ticket strip list. | Services should stack as strip-like rows. | Yes |

Make a new services variant when the source is actually a package comparison table, process timeline, or booking flow.

### Cards

| Variant | Looks Like | Use When | Builder |
| --- | --- | --- | --- |
| 1 | Balanced default layout. | Generic card grid with no strong special layout cue. | Yes |
| 2 | Larger, more expressive rhythm. | Cards should breathe more and often use denser 4-column rhythm. | Yes |
| 3 | Editorial style with softer framing. | Card grid should feel softer and more editorial. | Yes |
| 4 | Compact utility layout. | Compact card grid without subtitle emphasis. | Yes |
| 5 | Playful magazine cards with tinted surfaces and pill actions. | Resource/article cards with more magazine styling. | Yes |
| 6 | Product shelf cards with image-led framing and soft action buttons. | Product-like or shop-shelf card sets. | Yes |
| 7 | Bento-style resource board with dashed frames. | Mixed resource board with framed compartments. | Yes |
| 8 | Spotlight lead story followed by compact supporting cards. | One lead card plus supporting secondary cards. | Yes |

Make a new cards variant when the source needs masonry, filtering, nested subcards, or board logic not present here.

### Stats

| Variant | Looks Like | Use When | Builder |
| --- | --- | --- | --- |
| 1 | Balanced default layout. | Standard metric counters. | Yes |
| 2 | Larger, more expressive rhythm. | Counters should be bigger and more energetic. | Yes |
| 3 | Editorial style with softer framing. | Stats need a softer editorial treatment. | Yes |
| 4 | Compact utility layout. | Small compact stat row or grid. | Yes |
| 5 | Bold dark counters with colorful number accents. | High-contrast counter board. | Yes |
| 6 | Benchmark cards with accent rails. | Stats should feel like benchmark or KPI cards. | Yes |
| 7 | Centered chip counters with softer surfaces. | Softer centered stat chips. | Yes |
| 8 | Ribbon counters with a full-width lead highlight. | One lead stat plus supporting ribbon-style counters. | Yes |

Make a new stats variant when the source requires animated charts, progress bars, or diagrammatic data views rather than counters.

### Gallery

| Variant | Looks Like | Use When | Builder |
| --- | --- | --- | --- |
| 1 | Even grid tiles. | Regular balanced image grid. | Yes |
| 2 | Tighter crop with masonry rhythm. | Grid should feel slightly more varied than v1. | Yes |
| 3 | Gallery with caption emphasis. | Captions are part of the presentation, not just metadata. | Yes |
| 4 | Lead image first. | First image must dominate the set. | Yes |
| 5 | Playful polaroid board with slight tilt. | Images should feel pinned or scrapbook-like. | Yes |
| 6 | Quilted mosaic with hero tiles. | Mosaic grid with larger hero tiles; use `layoutMode=editorial-collage` for the explicit editorial collage. | Yes |
| 7 | Cinematic tiles with caption overlays. | Image categories or cinematic overlays; use `layoutMode=category-cards` for category-card behavior. | Yes |
| 8 | Framed studio grid with taller portrait rhythm. | Taller framed grid; use `layoutMode=social-grid` for Instagram-style social grid. | Yes |

Make a new gallery variant when the source needs a true masonry engine, before/after comparison, slideshow narrative, or zoom choreography beyond the current grid/collage patterns.

### Carousel

| Variant | Looks Like | Use When | Builder |
| --- | --- | --- | --- |
| 1 | One-band cinematic filmstrip. | Single horizontal filmstrip or image reel. | Yes |
| 2 | Fitzgerald-style two-band filmstrip with centered overlay. | Two stacked filmstrips with a central editorial overlay. | Yes |
| 3 | Scroll strip with edge fade. | Horizontal strip should feel lighter and more scroll-driven. | Yes |
| 4 | Image-forward, no-card style. | Minimal image-first slider without strong card framing. | Yes |
| 5 | Image-only gallery strip. | Mostly image-only carousel or logo/image strip. | Yes |
| 6 | Tilted photo cards. | Sliding photo cards with playful tilt. | Yes |
| 7 | Focus slider with clickable dots. | Carousel should expose dot-based selection. | Yes |
| 8 | Full-slide feature with side carets. | Larger feature-slide presentation with explicit side carets. | Yes |

Make a new carousel variant when the source needs synced thumbs, vertical slides, coverflow, or section-level choreography not present here.

### Datepicker

| Variant | Looks Like | Use When | Builder |
| --- | --- | --- | --- |
| 1 | Balanced month plus upcoming weeks. | Standard calendar/planner section. | Yes |
| 2 | Stronger month focus and compact week cards. | Calendar grid should dominate. | Yes |
| 3 | Week overview first with mini calendar support. | Weekly schedule is primary, month grid secondary. | Yes |
| 4 | Board layout for upcoming weeks with month summary above. | Planner should feel board-like. | Yes |
| 5 | Concierge planner with glassy panels. | Scheduling UI should feel upscale and glassy. | Yes |
| 6 | Agenda-first timeline with structured week cards. | Agenda/timeline view is the main pattern. | Yes |
| 7 | Playful chip planner with roomy calendar cells. | Lighter planner with chip-like markers. | Yes |
| 8 | Studio scheduler with reversed emphasis and bold cards. | More assertive studio-style scheduler. | Yes |

Make a new datepicker variant when the source needs a real booking engine, availability grid, or multi-step reservation logic.

### FAQ

| Variant | Looks Like | Use When | Builder |
| --- | --- | --- | --- |
| 1 | Straightforward accordion stack. | Standard FAQ accordion. | Yes |
| 2 | Two-column question cards. | FAQ should spread into a two-column card layout. | Yes |
| 3 | Support card beside accordion list. | Help/support card should sit next to questions. | Yes |
| 4 | Compact support-first knowledge block. | Support card is more important than a large accordion stack. | Yes |
| 5 | Glassy support rail beside roomy answer cards. | FAQ should feel more premium and glassy. | Yes |
| 6 | Playful answer board with dashed question cards. | Decorative FAQ board. | Yes |
| 7 | Support banner on top with spacious accordion stack. | Banner plus FAQ list structure. | Yes |
| 8 | Editorial split with crisp support card and vivid answers. | Split editorial FAQ treatment. | Yes |

Make a new FAQ variant when the source needs filtering, categories/tabs, or nested documentation navigation.

### Testimonials

| Variant | Looks Like | Use When | Builder |
| --- | --- | --- | --- |
| 1 | Fanned cards. | Review set should feel card-based and lightly playful. | Yes |
| 2 | Giant editorial pull quotes. | Quotes themselves are the hero element. | Yes |
| 3 | Dark spotlight quotes. | Testimonial section should feel dramatic and dark. | Yes |
| 4 | Story cards with label. | Review cards should read as customer stories. | Yes |
| 5 | Centered praise stage. | Centered praise presentation without stage-thumbnails behavior. | Yes |
| 6 | Polaroid wall. | Reviews should feel pinned or scrapbook-like. | Yes |
| 7 | Speech bubbles. | Reviews should visibly read as conversational speech bubbles. | Yes |
| 8 | Review strip list. | Default v8 is a review strip; use `layoutMode=review-stage` or `review-stage-thumbnails` for stage behavior. | Yes |

Make a new testimonials variant when the source needs video testimonials, timeline progression, or interactions beyond the current stage/grid/strip patterns.

### Content

| Variant | Looks Like | Use When | Builder |
| --- | --- | --- | --- |
| 1 | Alternating image plus text rows. | Standard editorial story section with alternating rhythm. | Yes |
| 2 | Editorial three-column split with movable image column. | More magazine-like editorial split; use `layoutMode=triptych-link-stack` for image/link/image composition. | Yes |
| 3 | Media-heavy emphasis. | Image/media should dominate more than the text. | Yes |
| 4 | Timeline-like card rows. | Story should read as sequential rows or steps. | Yes |
| 5 | Editorial slabs with warm color shifts. | Large slab-like editorial story sections. | Yes |
| 6 | Floating media cards with a playful tilt. | Story images should feel layered and floating. | Yes |
| 7 | Stacked story cards with media banners. | Narrative as stacked cards with banner-like media blocks. | Yes |
| 8 | Spotlight alternating rows with accent rails. | Alternating story rows with stronger highlighted rails. | Yes |

Make a new content variant when the source needs longform magazine layout, true multi-column editorial layout, sticky side navigation, or other article-specific structure.

### CTA

| Variant | Looks Like | Use When | Builder |
| --- | --- | --- | --- |
| 1 | Classic gradient banner. | Default conversion band. | Yes |
| 2 | Split headline and actions. | CTA should split copy and button/action rail. | Yes |
| 3 | Centered minimal callout. | Small focused CTA with minimal framing. | Yes |
| 4 | Concise utility CTA strip. | Utility CTA bar rather than a large feature. | Yes |
| 5 | Colorful split banner with strong action rail. | CTA needs more energy and a stronger action side. | Yes |
| 6 | Centered launch card with full-width buttons. | Strong centered card CTA; use `layoutMode=newsletter-split` or `overlay-card` when those exact structures are needed. | Yes |
| 7 | Dashed utility banner with punchier primary button. | Utility CTA with more punch and graphic framing. | Yes |
| 8 | Editorial two-panel callout with balanced actions. | Two-panel editorial CTA. | Yes |

Make a new CTA variant when the source is really a signup form, booking widget, or complex conversion module rather than a CTA band.

### Contact

| Variant | Looks Like | Use When | Builder |
| --- | --- | --- | --- |
| 1 | Clean concierge split with detail list. | Standard contact section with balanced split. | Yes |
| 2 | Form first, details second. | Form should be visually dominant. | Yes |
| 3 | Editorial sidebar composition. | Contact info should read more editorial/sidebar-like. | Yes |
| 4 | Compact stacked support section. | Smaller stacked contact section. | Yes |
| 5 | Offset poster composition with floating art card. | Visual/art-directed contact block. | Yes |
| 6 | Form-first with sticker-like detail chips. | Form plus playful detail chips. | Yes |
| 7 | Stacked editorial layout with airy form. | More spacious stacked contact section. | Yes |
| 8 | Speech-bubble split with accent form. | Contact section should feel more expressive and conversation-like. | Yes |

Make a new contact variant when the source needs appointment scheduling, map-led location discovery, or a multistep intake flow.

### Socialbar

| Variant | Looks Like | Use When | Builder |
| --- | --- | --- | --- |
| 1 | Compact balanced pills. | Compact social link group. | Yes |
| 2 | Card grid with icon on top. | Socials should present as mini cards. | Yes |
| 3 | Minimal inline text links. | Very light social row with text-first feel. | Yes |
| 4 | Circular icon rail plus labels below. | Icons should lead, labels secondary. | Yes |
| 5 | Colorful social tiles with equal rhythm. | Socials should read as equal-weight tiles. | Yes |
| 6 | Icon dock with stronger button blocks. | Social links should feel more like buttons. | Yes |
| 7 | Sticker-like cards with mixed rotation. | Social links should feel playful and handmade. | Yes |
| 8 | Centered footer-social rail with location heading. | Footer-adjacent social row. | Yes |

Make a new socialbar variant when the source needs feed embedding, social proof counts, or platform-specific media cards.

### Footer

| Variant | Looks Like | Use When | Builder |
| --- | --- | --- | --- |
| 1 | Classic horizontal footer nav. | Standard footer with links and optional CTA. | Yes |
| 2 | Center-weighted nav with softer CTA. | Slightly softer footer than v1. | Yes |
| 3 | Brand plus tagline emphasis. | Brand/tagline should matter in the footer. | Yes |
| 4 | Centered desktop footer navigation. | Footer menu should center visually. | Yes |
| 5 | Editorial split with right-aligned navigation. | Footer should feel editorial and asymmetric. | Yes |
| 6 | Centered stacked footer with prominent actions. | Brand and actions stack in the center. | Yes |
| 7 | Structured call-to-action footer with full-width links. | Footer is also a strong CTA block. | Yes |
| 8 | Centered credits rail with optional heading. | Footer should behave like a credits/closing rail. | Yes |

Make a new footer variant when the source needs mega-footer columns, newsletter embed behavior, or a structurally different footer information architecture.

## Scraper Strategy

Use this order:

1. choose block `type` from section role
2. choose `variant` from structural layout cues
3. choose `layoutMode` only when the guide explicitly says it changes the structure
4. if no entry matches structurally, mark the section as `needsNewVariant`

Recommended output fields for a scraper-side planner:

```json
{
  "type": "hero",
  "variant": 3,
  "layoutMode": "title-overlay",
  "patternExists": true,
  "builderEditable": true,
  "confidence": 0.94,
  "needsNewVariant": false,
  "reason": "Full-bleed background hero with centered overlay title and CTA."
}
```

