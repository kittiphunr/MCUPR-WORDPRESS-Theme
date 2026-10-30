# Versions and builds

- **Every update gets a new version, raised by one step.** The usual update raises the last number (1.80.0 → 1.80.1); a big change
  (a new feature area, a redesign, a change that moves things for the people who run the site) raises the second number and starts the
  last at 0 (1.80.1 → 1.81.0). The first number is raised only when asked.
- **Every update also gets a build number**, a counter that only goes up (`MCUPR_BUILD` in `mcupr/functions.php`). It is shown after the
  version – "MCUPR 1.80.1 (build 1056)" – in the footer, the About dialog and the control center, in the heading of the release in
  `README.md` (`## v1.80.1 (build 1056) – …`) and in the changelog line of `mcupr/readme.txt`, so a zip, a page and a pull request can be
  traced to one build.
- Use `bash scripts/bump-version.sh patch|minor "what changed"`; it sets the version and the build everywhere (style.css, functions.php,
  package.json, readme.txt). Write the README.md section with the version and the build it printed.
- `tests/versioning.sh` checks that the files agree and that the script steps the numbers as above.
