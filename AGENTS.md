## Agent skills

### Issue tracker

Issues and PRDs are tracked in GitHub with `gh`. See `docs/agents/issue-tracker.md`.

When posting multi-line GitHub issue comments from PowerShell, do not pass literal `\n` in `gh issue comment --body`; it renders as text. Send a JSON body with escaped newlines through `gh api --input -`, then verify the rendered body.

### Triage labels

Triage uses `needs-triage`, `needs-info`, `ready-for-agent`, `ready-for-human`, and `wontfix`. See `docs/agents/triage-labels.md`.

### Domain docs

This is a single-context repository. See `docs/agents/domain.md`.
