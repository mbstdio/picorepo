## Agent skills

### Issue tracker

Issues and PRDs are tracked in GitHub with `gh`. See `docs/agents/issue-tracker.md`.

When posting multi-line GitHub issue comments from PowerShell, do not pass literal `\n` in `gh issue comment --body`; it renders as text. Send a JSON body with escaped newlines through `gh api --input -`, then verify the rendered body.

Format GitHub comments as readable GitHub-flavored Markdown: use short paragraphs, bullet lists for changes and verification results, and backticks for commit hashes, commands, and file paths. Avoid packing an implementation summary and all checks into a single long line. Preserve real line breaks and Markdown characters when passing the body through PowerShell (double-quoted strings can consume backticks). After posting, fetch the comment with `gh api` and verify that its body contains the intended line breaks, lists, and code formatting; correct it immediately if formatting was lost.

### Triage labels

Triage uses `needs-triage`, `needs-info`, `ready-for-agent`, `ready-for-human`, and `wontfix`. See `docs/agents/triage-labels.md`.

### Domain docs

This is a single-context repository. See `docs/agents/domain.md`.
