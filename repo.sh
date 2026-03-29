#!/usr/bin/env bash

set -euo pipefail

if ! command -v git >/dev/null 2>&1; then
	echo "Error: git is not installed or not on PATH."
	exit 1
fi

if [[ ! -d .git ]]; then
	echo "Error: run this script from the root of a git repository."
	exit 1
fi

if [[ $# -lt 1 ]]; then
	echo "Usage: ./repo.sh \"your commit message\""
	exit 1
fi

commit_message="$*"

echo "Staging all changes..."
git add .

if git diff --cached --quiet; then
	echo "No staged changes to commit."
	exit 0
fi

echo "Creating commit..."
git commit -m "$commit_message"

current_branch="$(git rev-parse --abbrev-ref HEAD)"
echo "Pushing to origin/${current_branch}..."
git push -u origin "$current_branch"

echo "Done. Changes pushed successfully."
