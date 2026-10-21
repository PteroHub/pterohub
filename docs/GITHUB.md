# Publishing to GitHub

Repository: `https://github.com/PteroHub/pterohub.git`

## Files to commit

Commit the full contents of the source archive: `app/`, `bootstrap/`, `config/`, `database/migrations/`, `public/`, `resources/`, `routes/`, `artisan`, `composer.json`, `composer.lock`, `.env.example`, `.gitignore`, `README.md`, `docs/`, `CHANGELOG.md`, `CONTRIBUTING.md`, `SECURITY.md`, `VERSION`, and `.github/`.

**Never commit:** `.env`, `.env.production`, API keys, SMTP credentials, production database exports, user uploads, Laravel logs, backups, `vendor/` or `node_modules/`. The included `.gitignore` blocks common paths; check `git status` and `git diff --cached` anyway.

## Upload from a local working directory

Extract the archive into a **new clean folder**, then run:

```bash
git init -b main
git remote add origin https://github.com/PteroHub/pterohub.git
git add .
git status --short
git diff --cached --check
git commit -m "Prepare PteroHub RC 1.02 source and documentation"
git push -u origin main
```

If the remote repository already has commits, **do not force push**. Clone the existing repository first, copy the new source into it (preserving `.git/`), review the diff and commit. Resolve remote changes rather than overwriting history.

## Tagging

After the code and deployment are verified:

```bash
git tag -a rc1.02 -m "PteroHub RC 1.02"
git push origin rc1.02
```

Keep production secrets on the server or in an approved secrets manager, never in GitHub Actions YAML. The included workflow checks PHP/JavaScript syntax; it does not test database migrations or production behavior.
