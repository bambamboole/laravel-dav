---
name: worktrees
description: Use when creating, listing, or removing git worktrees for this package — e.g. running parallel branches, isolating an experiment, or executing a plan without disturbing the main checkout. Covers the .worktrees/ layout and per-worktree dependency install.
---

# Git Worktrees

Worktrees let you check out multiple branches at once in sibling directories that share one `.git`. Keep them under
`.worktrees/` (gitignored).

## Create

```bash
# new branch
git worktree add .worktrees/<name> -b <branch>

# existing branch
git worktree add .worktrees/<name> <branch>
```

Then install dependencies inside the worktree (each worktree has its own gitignored `vendor/`):

```bash
cd .worktrees/<name>
composer install
```

`php artisan` works immediately — the committed `artisan` symlink resolves against the worktree's own
`vendor/bin/testbench`. The package's migrations are loaded by the test suite against an in-memory SQLite database, so
there is no separate database to migrate or seed.

## List

```bash
git worktree list
```

## Remove

```bash
git worktree remove .worktrees/<name>
git worktree prune   # clean up records of manually deleted directories
```

Use `git worktree remove --force` if the worktree has uncommitted changes you intend to discard.
