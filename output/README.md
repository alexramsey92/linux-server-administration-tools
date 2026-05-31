# Output Directory

This directory is reserved for generated files and system outputs. It is not tracked by Git by default.

## Usage

- Generated reports
- System diagnostics output
- Exported data files
- Temporary processing files

## .gitignore

Files in this directory are not tracked by Git. If you need to commit specific files, force add them:

```bash
git add -f output/important-file
```

