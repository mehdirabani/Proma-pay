# Known Limitations

1. Final blind red-team accuracy is **86.5%**; 27/200 cases miss the required primary domain under distractor-heavy wording.
2. Security blind red-team recall is **85.42%** and FinTech recall is **87.5%**; this is below the robustness expected for unsupervised high-risk autonomy.
3. The local semantic fallback is latent-semantic TF-IDF/SVD based; it is not a general-purpose neural semantic model and can be distracted by unrelated context.
4. Confidence labels are heuristic, not calibrated probabilities.
5. External blind Agent A/B quality benchmark is **NOT VERIFIED**.
6. Deployment-model token measurement is **NOT VERIFIED**; no character-count token claim is made.
7. Repository runtime/version evidence depends on files and local executables that are actually accessible.
8. Remote CI execution is not proven by a local package build; only the workflow definition and local equivalent checks are available.
9. V1.2 runtime is intentionally frozen after Hidden evaluation, so the final red-team failures are not patched in this release. They should seed V1.3 development regressions.
