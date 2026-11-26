# CLAUDE.md

Project rules for the Contactgegevens Register Nextcloud app. These override the global guidelines where they conflict.

## Commits

- **Use [Conventional Commits](https://www.conventionalcommits.org/) syntax**: `<type>(<optional scope>): <description>`,
  e.g. `feat: add Contactgegeven entity`, `fix(api): reject empty BSN`. CI (`block-unconventional-commits`) rejects PRs
  with non-conforming commits. The usual rules still apply: brief (≤72 chars), imperative, atomic.
- **End every commit message with an `Assisted-by` trailer** naming the model that made the commit:

  ```
  feat: add Contactgegeven entity

  Assisted-by: ClaudeCode:claude-opus-5.5
  ```

  Use the actual model, e.g. `claude-opus-5.5`, `claude-fable-5.2`. This trailer replaces the
  "no Claude signature" rule from the global guidelines for this repo; still don't add `Co-Authored-By` or
  "Generated with Claude Code" lines.
- **All commits must be GPG-signed.** `commit.gpgsign` is enabled for this repo, so commits, amends and rebases sign
  automatically when gpg-agent has the passphrase cached.
  - After committing, check with `git log --show-signature -1` (or `--show-signature <base>..HEAD` after a rebase).
    Only if a commit ended up unsigned, remind Marc and give the command to fix it, e.g.
    `git commit --amend --no-edit -S` for the last commit, or
    `git rebase --exec 'git commit --amend --no-edit -S' <base>` for several.
  - If signing fails (e.g. pinentry can't prompt in a non-interactive session), stop and tell Marc. Never bypass it
    with `--no-gpg-sign` or `-c commit.gpgsign=false`.

## Naming

- **Domain entities are named in Dutch** (e.g. `Contactgegeven`, `Burger`), including their mappers, services and
  database tables derived from them. Framework and technical terms (Controller, Mapper, Service, etc.) stay English.
