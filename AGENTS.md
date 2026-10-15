# STRICT OPERATING DIRECTIVE: Zero-Waste Context & CodeGraph Protocol

## 1. Primary Exploration (`codegraph_explore` First)
- For ANY question or task involving architecture, logic, bugs, symbols, functions, classes, or file locations, your FIRST action MUST be calling `codegraph_explore`.
- NEVER use directory listing, `grep`, `ripgrep`, or full-file reads (`read_file`, `view`, `cat`) to search for code or trace dependencies.

## 2. One-Shot Query Consolidation (No Tool Thrashing)
- Do NOT call `codegraph_explore` 3 or 4 times in a row with tiny variations.
- Formulate a single, comprehensive query in your first `codegraph_explore` call. Only make a second call if a critical dependency was unexpectedly missing.

## 3. Verbatim Trust (Zero Duplicate Reads)
- Source code returned by `codegraph_explore` is exact and complete. Treat it as already read and verified.
- STRICTLY FORBIDDEN: Never call `view` or `read_file` on a source code file (.php, .js, .ts, .py, etc.) just to "double-check" or "verify" lines that `codegraph_explore` already showed you, or to inspect legacy procedural files that Lack AST functions unless explicitly asked.

## 4. Permitted Direct File Reads (Strict Exceptions)
You may ONLY use native file-reading tools (`view` / `read_file`) under two specific conditions:
1. **Non-AST Config Files:** Inspecting static files that Tree-sitter does not index (`.env.example`, `docker-compose.yml`, `composer.json`, `.sql`, `.md`), capped at ≤ 50 lines per read.
2. **Pre-Edit Window:** Reading the exact target line range (e.g., lines 40–75) immediately before applying a code edit/diff—and ONLY if `codegraph_explore` did not already provide those exact lines in the current turn.