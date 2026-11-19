# Coal Management - Folder Structure Documentation

## Overview

This application uses **Laravel Filament** as the admin panel framework with **Livewire** for reactive components. The system has **two distinct panels**:

| Panel | URL Path | Purpose | Users |
|-------|----------|---------|-------|
| **Admin Panel** | `/` | Internal staff management of clients, projects, tax reports | Admin/Staff |
| **Client Panel** | `/klien` | Client self-service dashboard for viewing their data | Clients |

---

## Filament Structure (`app/Filament/`)

```
app/Filament/
├── Client/                          # CLIENT PANEL (accessed at /klien)
│   └── Pages/                       # One page per portal section (PortalPage subclasses)
│       ├── PortalPage.php           # Base: renders one client.panel.* component, no Filament heading
│       ├── DashboardClient.php      # Ringkasan (/klien/ringkasan, home; old class name kept)
│       ├── Projects.php             # Proyek (/klien/proyek)
│       ├── Documents.php            # Dokumen (/klien/dokumen, badge = documents still to upload)
│       ├── TaxReports.php           # Laporan Pajak (/klien/laporan-pajak)
│       └── Profile.php              # Profil (/klien/profil)
│
├── Exports/                         # Data exporters
│   ├── ClientExporter.php
│   └── TaxReportExporter.php
│
├── Imports/                         # Data importers
│   ├── ClientImporter.php
│   └── IncomeTaxImporter.php
│
├── Pages/                           # ADMIN PANEL custom pages
│   ├── Dashboard.php                # Admin main dashboard
│   ├── DashboardTaxReport.php       # Tax report dashboard
│   ├── ProjectDetails.php           # Project detail view
│   ├── TaxChat.php                  # Tax chat feature
│   ├── ClientCommunication/         # Client communication CRUD
│   │   ├── Create.php
│   │   ├── Edit.php
│   │   └── Index.php
│   └── DailyTask/                   # Daily task management
│       ├── DailyTaskDashboard.php
│       └── DailyTaskList.php
│
├── Resources/                       # ADMIN PANEL CRUD resources
│   ├── AccountRepresentativeResource.php
│   ├── ApplicationResource.php
│   ├── ClientResource.php           # Client management (uses Management/ tabs)
│   ├── PicResource.php              # Person in Charge
│   ├── ProjectResource.php
│   ├── ProjectStepResource.php
│   ├── RequiredDocumentResource.php
│   ├── SopLegalDocumentResource.php
│   ├── SopResource.php
│   ├── SuggestionResource.php
│   ├── TaskResource.php
│   ├── TaxReportResource.php
│   ├── UserClientResource.php
│   └── UserLogResource.php
│   │
│   └── [Resource]/                  # Each resource has:
│       ├── Pages/                   # CRUD pages (Create, Edit, List, View)
│       ├── RelationManagers/        # Related data management
│       └── Widgets/                 # Resource-specific widgets
│
└── Widgets/                         # Global admin widgets
    ├── DocumentsOverview.php
    └── Clients/
        └── ClientBasicStatsWidget.php
```

### Key Filament Files

| File | Description |
|------|-------------|
| `Client/Pages/*.php` | Client portal sections (Ringkasan, Proyek, Dokumen, Laporan Pajak, Profil), one Filament page each; the selected company comes from `App\Support\ClientPortal\ClientContext` |
| `Resources/ClientResource.php` | Admin client management, uses `Client/Management/` Livewire tabs |
| `Pages/Dashboard.php` | Admin main dashboard |
| `Pages/DashboardTaxReport.php` | Admin tax report monitoring dashboard |

---

## Livewire Structure (`app/Livewire/`)

```
app/Livewire/
├── Client/                          # CLIENT-RELATED COMPONENTS
│   ├── Panel/                       # FOR CLIENT PANEL (/klien)
│   │   ├── OverviewTab.php          # Client dashboard overview
│   │   ├── ProyekTab.php            # Client's projects list
│   │   ├── DocumentTab.php          # Client's documents
│   │   ├── TaxReportTab.php         # Client's tax reports
│   │   └── TaxReport/               # Tax report sub-components
│   │       ├── TaxReportInvoices.php
│   │       └── Components/
│   │           └── InvoiceTable.php
│   │
│   └── Management/                  # FOR ADMIN PANEL (client detail tabs)
│       ├── IdentitasTab.php         # Client identity info
│       ├── PerpajakanTab.php        # Tax information
│       ├── KaryawanTab.php          # Client employees
│       ├── TimTab.php               # Assigned team
│       ├── ProjekTab.php            # Projects (admin view)
│       ├── DokumenTab.php           # Documents (admin view)
│       ├── KomunikasiTab.php        # Communication history
│       └── ComplianceTab.php        # Compliance tracking
│
├── DailyTask/                       # DAILY TASK MANAGEMENT
│   ├── Components/
│   │   ├── DailyTaskItem.php
│   │   ├── DailyTaskListComponent.php
│   │   └── KanbanBoardComponent.php
│   ├── Dashboard/
│   │   ├── DailyTaskStatus.php
│   │   ├── DailyTaskTimeline.php
│   │   ├── Filters.php
│   │   ├── StatsOverview.php
│   │   └── TasksByStatus.php
│   ├── Form/
│   │   └── DailyTaskFilterComponent.php
│   └── Modals/
│       └── DailyTaskDetailModal.php
│
├── Dashboard/                       # ADMIN DASHBOARD COMPONENTS
│   ├── Components/
│   │   └── GreetingCard.php
│   ├── DocumentClientModal.php
│   ├── OverdueProjects.php
│   ├── ProjectDetails.php
│   ├── StatsDetailModal.php
│   └── Widget/
│       └── ProjectStatsOverview.php
│
├── Projects/                        # PROJECT MANAGEMENT
│   ├── Components/
│   │   ├── ProjectClientLegal.php
│   │   ├── ProjectDetailComments.php
│   │   ├── ProjectDetailUser.php
│   │   ├── ProjectMember.php
│   │   └── ProjectPersonInCharge.php
│   ├── Forms/
│   │   └── CreateTaskComment.php
│   └── Modals/
│       ├── ApproveWithoutDocumentModal.php
│       ├── DocumentModal.php
│       └── GlobalDocumentModal.php
│
├── TaxReport/                       # TAX REPORT (ADMIN)
│   ├── Dashboard/
│   │   ├── Filters.php
│   │   ├── StatsOverview.php
│   │   ├── TaxCalendar.php
│   │   └── TopUnreportedClients.php
│   ├── Pph/                         # PPh (Income Tax)
│   │   ├── KaryawanList.php
│   │   ├── PphTaxList.php
│   │   └── TaxReportPph.php
│   ├── Ppn/                         # PPN (VAT)
│   │   ├── InvoiceTable.php
│   │   ├── TaxReportBupot.php
│   │   ├── TaxReportInvoices.php
│   │   ├── TaxReportKompensasi.php
│   │   └── YearlySummary.php
│   ├── TaxReportCountChart.php
│   └── TaxReportTypeChart.php
│
├── Notification/
│   └── NotificationButton.php
│
├── Profile/
│   └── UserProfile.php
│
├── UserDetail/
│   └── UserSubmittedDocuments.php
│
└── Widget/                          # REUSABLE WIDGETS
    ├── PersonInChargeProjectChart.php
    ├── ProjectPICChart.php
    ├── ProjectPropertiesChart.php
    ├── ProjectReportChart.php
    ├── RecentActivityTable.php
    └── RecentSubmittedDocuments.php
```

### Client Folder Distinction

| Folder | Panel | Usage | Blade Reference |
|--------|-------|-------|-----------------|
| `Client/Panel/` | Client Panel (`/klien`) | Components shown to clients | `@livewire('client.panel.*')` |
| `Client/Management/` | Admin Panel | Tabs in client detail view | `@livewire('client.management.*')` |

---

## Livewire Blade Views (`resources/views/livewire/`)

```
resources/views/livewire/
├── client/
│   ├── panel/                       # Client panel views
│   │   ├── overview-tab.blade.php
│   │   ├── proyek-tab.blade.php
│   │   ├── document-tab.blade.php
│   │   ├── tax-report-tab.blade.php
│   │   └── tax-report/
│   │       ├── tax-report-invoices.blade.php
│   │       └── components/
│   │           └── invoice-table.blade.php
│   │
│   └── management/                  # Admin client management views
│       └── components/
│           ├── identitas-tab.blade.php
│           ├── perpajakan-tab.blade.php
│           ├── karyawan-tab.blade.php
│           ├── tim-tab.blade.php
│           ├── projek-tab.blade.php
│           ├── dokumen-tab.blade.php
│           ├── komunikasi-tab.blade.php
│           └── compliance-tab.blade.php
│
├── daily-task/
│   ├── components/
│   ├── dashboard/
│   │   └── partials/
│   ├── form/
│   └── modals/
│
├── dashboard/
│   └── components/
│
├── projects/
│   ├── components/
│   ├── forms/
│   └── modals/
│       └── nested/
│
├── tax-report/
│   ├── dashboard/
│   ├── pph/
│   └── ppn/
│
├── notification/
├── profile/
├── user-detail/
└── widget/
```

---

## Filament Blade Views (`resources/views/filament/`)

```
resources/views/filament/
├── client/                          # CLIENT PANEL views
│   ├── pages/portal.blade.php       # Shared body of every portal page
│   ├── topbar-start.blade.php       # Crumb (desktop) / app tile + company picker (phones)
│   ├── switcher-hook.blade.php      # Company picker at the top of the sidebar
│   ├── mobile-nav.blade.php         # Bottom tab bar on phones
│   └── chat-floating.blade.php      # Floating chat button + compact ChatPanel
│
├── components/                      # Reusable Filament components
├── modals/
│   ├── application/
│   └── clients/
├── notifications/
│
├── pages/                           # ADMIN PANEL page views
│   ├── client-communication/
│   ├── daily-task/
│   ├── dashboard/
│   ├── projects/
│   └── tax-report/
│       └── components/
│
└── resources/                       # Resource-specific views
    ├── client-resource/
    │   └── pages/
    │       └── view-clients.blade.php   # Client detail with tabs
    ├── project-resource/
    │   └── pages/
    ├── suggestion-resource/
    │   └── widgets/
    └── taxReport-resource/
```

---

## Component Naming Convention

### PHP Namespace to Livewire Name Mapping

| PHP Class | Livewire Reference |
|-----------|-------------------|
| `App\Livewire\Client\Panel\OverviewTab` | `client.panel.overview-tab` |
| `App\Livewire\Client\Management\IdentitasTab` | `client.management.identitas-tab` |
| `App\Livewire\TaxReport\Pph\TaxReportPph` | `tax-report.pph.tax-report-pph` |
| `App\Livewire\Projects\Components\ProjectMember` | `projects.components.project-member` |

### Blade View Path Convention

| PHP Class Location | View Path |
|-------------------|-----------|
| `app/Livewire/Client/Panel/OverviewTab.php` | `resources/views/livewire/client/panel/overview-tab.blade.php` |
| `app/Livewire/TaxReport/Ppn/InvoiceTable.php` | `resources/views/livewire/tax-report/ppn/invoice-table.blade.php` |

**Rules:**
- Namespace segments become kebab-case folder names
- Class name becomes kebab-case file name
- All lowercase for folders and files

---

## Panel Entry Points

### Admin Panel (`/`)
- **Dashboard**: `app/Filament/Pages/Dashboard.php`
- **Client Detail**: `app/Filament/Resources/ClientResource/Pages/ViewClient.php`
  - Uses tabs from `app/Livewire/Client/Management/`

### Client Panel (`/klien`)
- **Pages**: `app/Filament/Client/Pages/` (Ringkasan, Proyek, Dokumen, Laporan Pajak, Profil)
  - Each page mounts one component from `app/Livewire/Client/Panel/`
  - Components use the `Concerns\UsesCurrentClient` trait: the company id is locked and comes from `ClientContext` (session, checked against `user_clients`), never from the browser
  - Styles: `resources/css/filament/admin/client-portal/*.css`; mockup `docs/mockups/client-portal-redesign.html`

---

## Feature Modules

| Module | Admin Components | Client Components |
|--------|------------------|-------------------|
| **Tax Report** | `TaxReport/Dashboard/`, `TaxReport/Pph/`, `TaxReport/Ppn/` | `Client/Panel/TaxReport/` |
| **Projects** | `Projects/Components/`, `Projects/Modals/` | `Client/Panel/ProyekTab.php` |
| **Documents** | `Client/Management/DokumenTab.php` | `Client/Panel/DocumentTab.php` |
| **Daily Tasks** | `DailyTask/` | - |

---

This documentation reflects the current restructured folder organization where client-facing components are clearly separated from admin management components.
