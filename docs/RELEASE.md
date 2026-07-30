# Release process

1. Update [CHANGELOG.md](CHANGELOG.md): move entries from `[Unreleased]` to a new `[X.Y.Z] - YYYY-MM-DD` section. (This project does not store version in `composer.json`; Packagist uses the git tag.)
2. Update [UPGRADING.md](UPGRADING.md) if the release has upgrade notes.
3. Run pre-release checks: `make release-check` (includes `check-no-cursor-coauthor`, `check-open-prs`, cs-fix, cs-check, rector-dry, phpstan, test-coverage, and optionally demo healthchecks).
4. Complete the [Release security checklist (12.4.1)](SECURITY.md#release-security-checklist-1241).
5. Commit all changes, create an annotated tag (e.g. `v1.0.0`), and push branch and tag. The release workflow will create the GitHub Release with the changelog.
6. Publish the package to Packagist if applicable (usually automatic when the tag is pushed).

After creating the release commit and tag, run `make check-no-cursor-coauthor` again **before** `git push` (REQ-GIT-001). The release commit itself is not covered by an earlier `release-check` run.

## Example for v1.0.0

```bash
git add -A
git status   # review
git commit -m "Release 1.0.0: initial QrCodeBundle"
git tag -a v1.0.0 -m "Release 1.0.0: initial QrCodeBundle"
make check-no-cursor-coauthor
git push origin main
git push origin v1.0.0
```
