# Delivery batches

Work on shared causes across component families before building the showcase.
Keep native capabilities in PAM Native, CLI capabilities in PAM, and visual
composition in PAM Native UI. Do not add decorative preview cards.

For each batch:

1. Inspect the current diff and existing evidence; do not repeat completed work.
2. Implement related fixes and regression cases together.
3. Run the render matrix and static analysis once against the batch.
4. Build one Android candidate and exercise the changed interactions and layouts.
5. Retest failed or subsequently changed scopes, not unrelated successful cases.
6. Record the candidate identity, actual coverage, remaining gaps and deliverable.

Screenshots alone are not interaction approval. Matrix coverage is not device
coverage. A local commit is not a published release. Release gates remain required.
Avoid repeated CI polling and retain only the build artifacts needed for the
current candidate and evidence; never delete unrelated user files or devices.
