#!/usr/bin/env bash
set -euo pipefail

if [[ $# -lt 1 ]]; then
  echo "Usage: generate_page.sh <token>" >&2
  exit 1
fi

token="$1"

if [[ ! "$token" =~ ^[A-Za-z0-9_-]+$ ]]; then
  echo "Invalid token." >&2
  exit 1
fi

base_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
page_dir="$base_dir/pages/$token"
prompt_path="$page_dir/prompt.txt"
log_dir="$base_dir/logs"
log_file="$log_dir/page_generate_${token}.log"
status_file="$log_dir/page_generate_${token}.status"
glob_log="$log_dir/page_generate_glob.log"
runtime_dir="$base_dir/.opencode_runtime"

if [[ ! -d "$page_dir" ]]; then
  echo "Page directory not found: $page_dir" >&2
  exit 1
fi

mkdir -p "$log_dir"
touch "$log_file" "$status_file"
touch "$glob_log" 2>/dev/null || true

if [[ ! -w "$log_file" || ! -w "$status_file" || ! -w "$glob_log" ]]; then
  if [[ $(id -u) -eq 0 ]]; then
    chown www-data:www-data "$log_file" "$status_file" "$glob_log" 2>/dev/null || true
    chmod 664 "$log_file" "$status_file" "$glob_log" 2>/dev/null || true
  fi
fi

if [[ ! -w "$log_file" || ! -w "$status_file" ]]; then
  echo "Log/status files are not writable." >&2
  exit 1
fi

exec >> "$log_file" 2>&1

mkdir -p "$runtime_dir"
export HOME="$runtime_dir"
export XDG_CACHE_HOME="$runtime_dir/.cache"
export XDG_CONFIG_HOME="$runtime_dir/.config"
export XDG_DATA_HOME="$runtime_dir/.data"

if [[ ! -f "$prompt_path" ]]; then
  echo "Prompt file not found: $prompt_path" >&2
  exit 1
fi

if [[ ! -f "$page_dir/reports.csv" && ! -f "$page_dir/report.csv" ]]; then
  echo "CSV file not found in $page_dir" >&2
  exit 1
fi

timestamp() {
  date -u '+%Y-%m-%dT%H:%M:%SZ'
}

log_status() {
  local status="$1"
  local message="$2"
  if [[ -w "$glob_log" ]]; then
    echo "$(timestamp) token=${token} status=${status} message=${message}" >> "$glob_log"
  fi
}

on_exit() {
  local exit_code=$?
  if [[ $exit_code -ne 0 ]]; then
    echo "failed" > "$status_file"
    log_status "failed" "exit_code=${exit_code}"
  fi
}

trap on_exit EXIT

echo "running" > "$status_file"
log_status "running" "start"

opencode_bin=""
if [[ -n "${OPENCODE_BIN:-}" && -x "$OPENCODE_BIN" ]]; then
  opencode_bin="$OPENCODE_BIN"
elif command -v opencode >/dev/null 2>&1; then
  opencode_bin="$(command -v opencode)"
elif [[ -x "/root/.opencode/bin/opencode" ]]; then
  opencode_bin="/root/.opencode/bin/opencode"
elif [[ -x "/usr/local/bin/opencode" ]]; then
  opencode_bin="/usr/local/bin/opencode"
elif [[ -x "/usr/bin/opencode" ]]; then
  opencode_bin="/usr/bin/opencode"
fi

if [[ -z "$opencode_bin" ]]; then
  echo "opencode command not found." >&2
  exit 1
fi

SYSTEM_PROMPT=$(cat <<'EOF'
You are generating a web page inside a sandbox.
Only create or modify app.html and app.js in the current folder.
Do not create or modify app.css.
Do not create or modify any other files.
The page already includes Bootstrap, jQuery, Font Awesome, and SweetAlert2 (Swal).
Use those libraries where helpful and do not add external dependencies.
Use the CSV data file in this folder (reports.csv or report.csv) as the dataset for the page.
The page renderer injects app.css and app.js automatically.
Do not add <link> tags or <script src> tags that reference app.css or app.js in app.html.
Prefer app.html to contain only the body markup (no <html>, <head>, or <body> tags).
Do not include inline <script> tags in app.html.
EOF
)

prompt_text="$(cat "$prompt_path")"
final_prompt="$(printf "%s\n\n%s" "$SYSTEM_PROMPT" "$prompt_text")"

cd "$page_dir"
rm -f "app.html" "app.css" "app.js"
cat /dev/null > "$page_dir/app.css"
"$opencode_bin" run "$final_prompt"

missing_files=()
for file in "app.html" "app.css" "app.js"; do
  if [[ ! -f "$page_dir/$file" ]]; then
    missing_files+=("$file")
  fi
done

if [[ ${#missing_files[@]} -gt 0 ]]; then
  if [[ ${#missing_files[@]} -eq 1 && "${missing_files[0]}" == "app.js" ]]; then
    cat > "$page_dir/app.js" <<'EOF'
document.addEventListener('DOMContentLoaded', function () {
  // JS stub generated because no script was provided.
});
EOF
    log_status "warning" "missing_app_js_stubbed"
  else
    echo "failed" > "$status_file"
    log_status "failed" "missing_files=${missing_files[*]}"
    echo "Missing generated files: ${missing_files[*]}" >&2
    exit 1
  fi
fi

if [[ ! -f "$page_dir/app.html" || ! -f "$page_dir/app.css" || ! -f "$page_dir/app.js" ]]; then
  echo "failed" > "$status_file"
  log_status "failed" "missing_files=post_stub_check"
  echo "Missing generated files after stub check." >&2
  exit 1
fi

echo "success" > "$status_file"
log_status "success" "completed"
