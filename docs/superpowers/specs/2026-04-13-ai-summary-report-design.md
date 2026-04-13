# Design Spec: AI Strategic Summary Report Refactor

**Date**: 2026-04-13
**Topic**: AI Summary Report Refactor (PDF Template Alignment)
**Status**: DRAFT - Pending User Review

## Overview
Refactor the current AI Summary Report feature to align with the "Client Account Snapshot" template (`summary_tempate.pdf`). The goal is to transform the existing flat text summary into a multi-sectioned, executive-ready PDF that synthesizes both conversation data and CRM activity streams.

## Problem Statement
The current AI summary is too simplified (summary, insights, actions) and doesn't capture the full strategic picture of an account. Leadership requires a report that includes financial health (ARR/MRR), relationship mapping, and cross-channel activity (Lead/Opportunity streams).

## Proposed Design

### 1. Data Aggregation (Hybrid Approach)
To ensure 100% data integrity for financial metrics while leveraging AI for strategic synthesis:
- **Hard CRM Data (SQL)**: Fetched directly from `leads`, `contacts`, and `opportunities`.
- **Activity Streams**: Aggregated from `LeadActivity` and `OpportunityActivity` logs.
- **Conversation Context**: Extracted from the `EmailThread` snippets.

### 2. Adaptive Scoping Model
Users will be able to select from four distinct report scopes in the UI:
1.  **Overall**: Full synthesis of Lead and all linked Opportunities.
2.  **Leads Only**: Focuses strictly on the lead's top-of-funnel engagement and conversion intent.
3.  **All Opportunities**: Aggregates state and signals across all deals in the account.
4.  **Specific Opportunity**: A deep-dive into one selected deal, its blockers, and its financial status.

### 3. AI Prompt Architecture (`ReportPromptFactory`)
Update the backend intelligence to request a structured JSON payload matching the PDF's sections:
- **Account Status**: AI-inferred health/status based on activity patterns.
- **Executive Insights**: 3-5 strategic bullets covering revenue risk, adoption, and momentum.
- **Key Relationships**: An array of identified stakeholders, roles, and relationship strength.
- **Risks & Opportunities**: Specific commercial and operational highlights.
- **Role-Based Actions**: Targeted next steps for Sales, CSM, and Support.

### 4. Professional PDF Generation
- **Engine**: `barryvdh/laravel-dompdf`.
- **Template**: A new Blade view (`resources/views/reports/ai_summary_pdf.blade.php`) styled with "Print CSS" to replicate the layout of the provided PDF template (headers, tables, horizontal separators).
- **Download Flow**: Triage card button triggers a backend stream that marries the CRM data + AI JSON and returns a `.pdf`.

## Success Criteria
- [ ] Users can dynamically select specific Opportunities from a dropdown in the AI Sidebar.
- [ ] The generated PDF contains accurate ARR/MRR/Deal data from the CRM.
- [ ] The AI insights are "State-Aware" (e.g., [CLOSED LOST] prefixing if thread is dead).
- [ ] The layout matches the styling of `docs/summary_tempate.pdf`.

## Error Handling
- **Missing Data**: If no opportunities exist, the "Deals" section will display "No active deals found" rather than leaving a blank space.
- **AI Latency**: The frontend will display a loading state until the backend completes the synchronous AI call and PDF render.
- **Provider Failure**: Graceful fallback to a simplified report if the LLM fails to return the full JSON schema.
