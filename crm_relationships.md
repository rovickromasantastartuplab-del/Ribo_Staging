# CRM Relationship Architecture

This document outlines the core structural relationships between Leads, Accounts, Contacts, and Opportunities within the CRM. This acts as a primary architectural rule when creating or modifying features involving these entities.

## 1. Leads (The Starting Point)
A **Lead** represents a raw prospect or a person/company you haven't engaged in meaningful business with yet. 
- **Data Structure:** Leads contain standalone data (Name, Email, Company Name, Phone, etc.).
- **Conversion Rule:** When a Lead is "Converted" (qualified for business), the system extracts its data and transforms it into two separate entities: an **Account** and a **Contact**. 
- *Note:* Once converted, the standalone Lead record is complete and future interactions are logged against the resulting Account/Contact.

## 2. Accounts (The Company/Organization)
An **Account** represents the overall business, organization, or umbrella entity you are selling to.
- **Data Structure:** Holds company-wide information (Industry, Billing/Shipping Addresses, Website).
- **Relational Role:** The Account acts as the central hub. It can have **many** Contacts, **many** Opportunities, and **many** Quotes/Invoices linked to it.

## 3. Contacts (The People)
A **Contact** is a specific individual or employee who works *at* an Account.
- **Relational Role:** Every Contact belongs to **one** specific Account. 
- *Example:* If the Account is "Microsoft", the Contact is "Bill Gates".

## 4. Opportunities (The Deal/Sale)
An **Opportunity** represents a potential sale, project, or deal that is currently in progress.
- **Relational Role:** An Opportunity is a transaction linked to **both**:
  1. An **Account** (the company buying the service/product).
  2. A **Contact** (the specific person at the company you are negotiating the deal with).
- *Rule of Thumb:* An Account can have multiple simultaneous Opportunities (e.g., selling them a Website Rebuild and an SEO Package at the same time), but each Opportunity belongs to exactly one Account and Contact.

## Summary of the Flow
1. **Lead** (Acquisition)
2. **Convert** → **Account** (The Company) + **Contact** (The Decision Maker)
3. **Opportunity** (The Deal linked to the Account & Contact)
