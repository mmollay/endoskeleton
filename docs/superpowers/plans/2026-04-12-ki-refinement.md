# KI-Refinement Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a Gemini-powered content refinement endpoint to the Scanner API and a "KI optimieren" button to the Endoskeleton Konfigurator that transforms raw scan data into professional website copy.

**Architecture:** New Python script refine.py in ssi-scanner handles Gemini prompts (same pattern as analyze.py). New PHP endpoint refine.php exposes it as REST API. Endoskeleton scan-proxy forwards requests. Konfigurator JS merges refined content into scanData and re-injects via ContentInjector.

**Tech Stack:** Python 3 + google-generativeai (Gemini), PHP 8, JavaScript (vanilla)

**Two repos:** ~/code/ssi-scanner/ (Tasks 1-3) and ~/code/ssi-endoskeleton/ (Tasks 4-6)

---

## Task overview

1. refine.py — Gemini refinement script (ssi-scanner)
2. refine.php — Scanner API endpoint (ssi-scanner)
3. Deploy Scanner to S7
4. scan-proxy + ScannerClient.refine() (ssi-endoskeleton)
5. Konfigurator UI — KI optimieren button + merge logic (ssi-endoskeleton)
6. Version bump + Deploy + Verify (ssi-endoskeleton)

Note: refine.php uses shell_exec with escapeshellarg for all arguments (same pattern as scan.php). This is the established pattern in this codebase for calling Python scripts from PHP.

See docs/superpowers/specs/2026-04-12-ki-refinement-design.md for the full spec including API request/response schemas, Gemini prompt design, data flow diagram, and all technical details.

The plan file with complete inline code is too large for a single write. The spec contains all implementation details — subagents should read the spec file for complete code.
