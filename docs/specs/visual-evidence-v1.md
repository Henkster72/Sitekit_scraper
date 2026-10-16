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
  repeated-item signals, backgrounds, computed visual observations, image
  statistics, crop status, candidate labels, and confidence/evidence. A
  section remains evidence even when its screenshot crop is skipped because
  the DOM geometry is outside or invalid for the captured image.
- `themeEvidence`: measured palette and exposed CSS variables, observed
  typography, geometry, density/rhythm, surface order, imagery character, and
  contrast

`roleCandidates` and `patternCandidates` are observations or classifier hints.
Their labels are not canonical catalog identities. Numeric `type` and `variant`
fields are deliberately absent from this contract.

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
  "themeEvidence": {"palette": {}, "typography": {}, "geometry": {}}
}
```

The normal downstream command is the Main SiteKit mapper. The scraper's
legacy mapper/draft exporter remains only as a compatibility artifact until
that consumer is switched to this handoff.
