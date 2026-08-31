---
description: "Use when editing README.md, readme.md, readme.txt, or other user-facing documentation for this repository. Covers readme wording constraints, WordPress readme format, changelog prose style, and monorepo package-specificity requirements."
applyTo: "README.md, readme.md, readme.txt"
---

# Documentation Rules

- Treat `readme.txt` as a WordPress.org plugin readme using WordPress readme syntax, not generic plain text or GitHub Markdown.
- Preserve the existing WordPress readme section markers and formatting conventions in `readme.txt`.
- Treat `readme.md` and similar repository documentation as product or package documentation, not as a WordPress.org `readme.txt`, unless the file being edited is explicitly a plugin readme.
- Preserve the existing Markdown heading structure and formatting conventions in `readme.md`.
- Because Troy is a monorepo, keep the target package explicit in documentation. Do not blur Troy, Troy Server, Troy Client, Troy Client Daemon, and Troy Installer behavior together.
- When documenting commands, settings, endpoints, package names, or file paths, keep the exact names used in the codebase or UI.
- Treat `readme.txt` changelog entries as Troy package release notes. Preserve the existing house style: version headings (`= x.x.1184 =`) and simple past-tense bullets (`Added`, `Fixed`, `Changed`, `Improved`, `Resolved`, `Removed`).
- Preserve the existing nested list and indentation style in `readme.txt` changelogs, including indented follow-up bullets where the current format uses them.
- When copying content from code, such as docblocks, comments, or commit notes, into `readme.txt`, `readme.md`, or other user-facing docs, preserve the essence verbatim. Only minor prose tweaks for readability are allowed. Do not add details that are not present in the source.
