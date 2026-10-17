# visual-evidence.v1

`visual-evidence.v1` is the handoff from `SiteKit_scraper` to Main SiteKit.
It describes what the source site visibly contains at captured viewports. It
does not emit OpenSite, SiteKit blocks, catalog IDs, or numeric renderer
variants.

Main SiteKit is responsible for interpreting this evidence into its canonical
OpenSite grammar, stable pattern IDs, ThemeChooser design language, and
renderer mapping.

## Shape

- `format` and `version`: contract identity (`sitekit-visual-evidence`, `1.0`)
- `source`: source URL, final URL, capture time, source type, and artifact paths
- `viewports`: desktop and mobile dimensions, page dimensions, screenshots,
  source HTML, and ordered viewport section evidence
- `sections`: merged section observations with stable evidence IDs, locators,
  viewport-linked bounding boxes and visibility, text, actions, media, forms,
  repeated-item signals and item ownership, navigation observations,
  backgrounds, computed visual observations, image statistics, optional dominant descendant surfaces, optional underlying
  document surfaces, crop status, candidate labels, and confidence/evidence. A
  section remains evidence even when its screenshot crop is skipped because
  the DOM geometry is outside or invalid for the captured image.
- `documentSurfaces`: optional rendered html/body/underlying document surface
  observations, kept separate from transparent section roots.
- `themeEvidence`: measured palette and exposed CSS variables, observed
  typography, geometry, density/rhythm, surface order, imagery character,
  contrast, and optional spatial surface evidence

`roleCandidates` and `patternCandidates` are observations or classifier hints.
Their labels are not canonical catalog identities. Numeric `type` and `variant`
fields are deliberately absent from this contract.

`repeatedItems` may contain peer item records for an interactive or repeated
visual collection. Item records retain observed bbox, media, text evidence,
actions, and diagnostic visibility/state; strongly evidenced inactive peers may
remain present even when their current CSS state is hidden. No SiteKit role is
assigned. `navItems` is similarly limited to observed compact navigation links;
Main SiteKit decides whether and how those links become canonical navigation.

## Example handoff

```json
{
  "format": "sitekit-visual-evidence",
  "version": "1.0",
  "source": {"url": "https://example.test/", "sourceType": "showit"},
  "viewports": {
    "desktop": {"width": 1440, "height": 900, "sections": []},
    "mobile": {"width": 390, "height": 844, "sections": []}
  },
  "sections": [],
  "documentSurfaces": {},
  "themeEvidence": {"palette": {}, "typography": {}, "geometry": {}}
}
```

`dominantSurfaces` is additive evidence, not a flattened site palette. It is
limited to visible geometrically significant CSS/vector/gradient surfaces and
records source kind, coverage relative to its section, and browser bbox.
The current generic significance thresholds are at least 3% visible area, or
at least 55% of the section width with an 80px visible height, with both
effective dimensions at least 24px. Coverage is visible element area divided
by section area; overlapping layers may therefore sum above `1.0`. Media
pixels are not sampled into this field.

The normal downstream command is the Main SiteKit mapper. The scraper's
legacy mapper/draft exporter remains only as a compatibility artifact until
that consumer is switched to this handoff.
