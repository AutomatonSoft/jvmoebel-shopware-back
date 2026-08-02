# AGENTS.md

Instructions apply to the entire repository.

## Project rules

Before changing code, read the documentation relevant to the task:

- [Architecture](docs/ARCHITECTURE.md) — plugin boundaries, layers, dependencies and placement of code;
- [Engineering guidelines](docs/ENGINEERING.md) — code style, design principles, validation, errors, logging, testing and security;
- [Backend workflow](docs/WORKFLOW.md) — implementation flow, required checks and handoff requirements;
- [Specifications](docs/specs/README.md) — when a specification is required and how it is maintained.

Project-wide architecture, infrastructure, migration and cross-repository decisions are maintained in `jvmoebel-shopware-docs`.

These documents are mandatory. Do not replace their rules with personal conventions. New code must follow the established style and patterns of the surrounding project code. If existing code conflicts with the documentation, follow the documentation and report the conflict.

## Working approach

- Inspect the affected plugin, related tests and specification before editing.
- Keep each change limited to the requested behavior. Do not combine it with unrelated refactoring, dependency updates or repository-wide formatting.
- Preserve existing user changes and do not overwrite code outside the task scope.
- Reuse existing project patterns before introducing a new abstraction, dependency, plugin or directory structure.
- Do not modify Shopware core, `vendor/`, generated files or third-party plugins unless the task explicitly requires a reviewed exception.
- Update the relevant specification and documentation when behavior, data, API, messages or architectural boundaries change.
- Ask for a decision when an unresolved choice changes public behavior, data compatibility or architecture. For local implementation details, follow the existing project conventions and continue.
- Do not commit, amend, push, deploy or modify external systems unless explicitly requested.

## Verification and handoff

- Run the checks required by [Backend workflow](docs/WORKFLOW.md) and any additional tests relevant to the changed behavior.
- Treat a failing check as part of the task: diagnose it and fix failures caused by the change.
- Never claim that a check passed if it was not executed. State the exact command and reason when a check cannot run.
- At handoff, summarize the implemented behavior, changed files, executed checks and any remaining risks or decisions.
