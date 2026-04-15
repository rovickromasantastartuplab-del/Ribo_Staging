# AI Implementation Map: Backend Integration Guide

This document serves as the technical source of truth for connecting the AI Assistant frontend to real-world backend services.

## 1. Complete AI Backend Requirements Map

| # | Component | UI Element | Type | Backend Requirement | API / Data Source |
| :--- | :--- | :--- | :--- | :--- | :--- |
| 1 | **Header** | **Deal Prob. %** | Value | Fetch probability score for thread | `TBD` |
| 2 | **Header** | **Mood (STABLE / ??)** | Value | Fetch engagement pulse trend | `TBD` |
| 3 | **Header** | **Refresh Sync** | Button | Re-run AI analysis on latest emails | `TBD` |
| 4 | **Strategic Action** | **Recommendation** | Text | Generate coaching quote via LLM | `TBD` |
| 5 | **Strategic Action** | **Goal & Reason** | Text | Return context for the recommendation | `TBD` |
| 6 | **Triage Card** | **Intent & Priority** | Badge | Classify intent/urgency of thread | `TBD` |
| 7 | **Triage Card** | **AI Summary** | Text | Generate natural language summary | `TBD` |
| 8 | **Triage Card** | **Status Update** | Button | Update Lead/Opportunity status | `TBD` |
| 9 | **Triage Card** | **Manual Overrides** | Menu | Save human corrections to database | `TBD` |
| 10 | **Triage Card** | **Report Scope** | Selector | Param for report generation scope | `TBD` |
| 11 | **Triage Card** | **Download Report** | Button | Generate & stream PDF summary | `TBD` |
| 21 | **Memory Card** | **Relationship Summary** | Text | Fetch long-term narrative summary | `TBD` |
| 22 | **Memory Card** | **Task Checklist** | List | Fetch extracted open commitments | `TBD` |
| 23 | **Memory Card** | **Checklist Toggle** | Toggle | Save "completed" state of tasks | `TBD` |
| 24 | **Drafting Hub** | **Prompt Input** | Input | Instruction string for LLM draft | `TBD` |
| 25 | **Drafting Hub** | **Tone Selector** | Toggle | System instruction for LLM style | `TBD` |
| 26 | **Drafting Hub** | **Generate Button** | Button | Send prompt/tone/thread to LLM | `TBD` |
| 27 | **Drafting Hub** | **Insert Button** | Button | Inject content to editor (Frontend) | `N/A (Frontend Only)` |
