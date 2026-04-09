# AI Usage Graph Design - SuperAdmin Dashboard

This document outlines the design for implementing a comprehensive AI usage tracking and visualization system within the SuperAdmin dashboard.

## Overview
The goal is to provide SuperAdmins with visibility into the system-wide AI resource consumption (OpenAI/GPT), including token counts, estimated costs, and company-level breakdowns.

## Proposed Components

### 1. Data Layer (Backend)
- **Aggregator**: Add a `getAiUsageInsights` method to `DashboardController.php`.
- **Metrics**: 
    - `daily_usage`: Time-series data for the last 30 days (date, tokens, requests, cost).
    - `model_distribution`: Count of tokens/requests per model version.
    - `top_companies`: List of 5 companies with highest token usage + their names.
    - `global_stats`: Total tokens, Total cost, Global success rate.
- **Cost Calculation**: Implement a simple price mapping service/utility to calculate the `estimated_cost` based on `model_version` and token counts (prompt vs completion).

### 2. UI Components (Frontend)
- **`AiUsageInsights.tsx`**: A new high-level component to be integrated into the SuperAdmin dashboard.
- **KPI Row**: 4 Summary cards using `Card` and `Lucide` icons.
- **Interactive Graph**: 
    - A `Recharts` `AreaChart` with a polished gradient.
    - `Tabs` (Radix UI) to switch between **Tokens**, **Requests**, and **Costs**.
- **Breakdown Row**:
    - **Models**: `PieChart` showing usage by model.
    - **Users**: A list/table of top-consuming companies.

## Data Schema (JSON Response)
```json
{
  "stats": {
    "totalTokens": 1250000,
    "totalRequests": 450,
    "totalCost": 12.45,
    "successRate": 98.5
  },
  "charts": {
    "dailyTrends": [
      { "date": "2026-04-01", "tokens": 12000, "requests": 5, "cost": 0.12 },
      ...
    ],
    "modelDistribution": [
      { "name": "gpt-4o-mini", "value": 850000, "color": "#3b82f6" },
      { "name": "gpt-4o", "value": 400000, "color": "#10b77f" }
    ]
  },
  "topCompanies": [
    { "name": "Tech Corp", "usage": "450k tokens", "cost": 4.50 },
    ...
  ]
}
```

## Placement
The AI Usage section will be placed immediately below the primary metrics row in the SuperAdmin dashboard to ensure high visibility without displacing core company management features.

## Verification Plan
1. **Database**: Seed `ai_usage_logs` with mock data for the last 30 days.
2. **Backend**: Verify the controller correctly aggregates daily sums and joins with the `users` table for company names.
3. **Frontend**: Verify the chart correctly switches between different data sets (Tokens/Requests/Cost) and responds to window resizing.
