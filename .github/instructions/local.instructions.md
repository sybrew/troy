---
description: "Use when handling local-only workspace guidance, private support or outreach materials, related-project cross-references, remediation, troubleshooting, reproduction, triage, or customer-facing diagnosis that may require code changes."
applyTo: ".local/**"
---

# Local Workspace Rules

- `.local/` is gitignored. Agent Grep and Glob cannot search it, even with an explicit path. `.cursorignore` `!.local/**` does not fix those tools. Use Read/Write with an explicit path, or Shell (`Get-ChildItem`, `Select-String`, `rg --no-ignore-vcs`). A zero Grep/Glob result does not mean `.local` is empty.
- Treat `.local/` as private workspace-only context.
- Do not expose `.local/` details or related-project context in public-facing instructions or responses unless the user asks for them.
- Keep private workspace-only guidance, partner or outreach rules, and related-project cross-references in `.local/.instructions/*.instructions.md`, not in tracked repo instruction files.
- Troy is a monorepo. When local guidance refers to Troy, Troy Server, Troy Client, Troy Client Daemon, or Troy Installer, keep the target package explicit.
- Treat support, partner, or outreach inquiries as first-class engineering work.
- These inquiries may require code inspection, reproduction, remediation, product explanation, or patches.
- Do not assume an inquiry is explanation-only.
- Diagnose whether the issue is configuration, expected behavior, a defect, a documentation gap, or a feature gap before recommending action.
- Fix the cause, not the symptom.
- Prefer the smallest safe remediation that prevents end-user issues.
- If the issue cannot be resolved from current context, gather the missing evidence or ask precise follow-up questions.

## Load `.local` instruction files

`.local/.instructions/*.instructions.md` is not auto-injected. Do not Read them all.

1. From the repo root, extract YAML frontmatter only (commands below).
2. Read the files whose `applyTo` matches the current paths, or whose `description` matches the task. Files with no `applyTo` still match on `description`.
3. Do not paste the extract or private bodies into chat.

### Windows

```powershell
Get-ChildItem -LiteralPath '.local/.instructions' -Filter '*.instructions.md' | ForEach-Object {
	$raw = [System.IO.File]::ReadAllText($_.FullName)
	if ($raw -match '(?s)\A---\r?\n(.+?)\r?\n---') {
		Write-Output ("FILE: {0}`n{1}`n" -f $_.Name, $Matches[1].Trim())
	}
}
```

### macOS / Linux

```bash
for f in .local/.instructions/*.instructions.md; do
	[ -f "$f" ] || continue
	echo "FILE: ${f##*/}"
	awk 'BEGIN{c=0} /^---$/ {c++; if(c==1) next; if(c==2) exit} c==1' "$f"
	echo
done
```
