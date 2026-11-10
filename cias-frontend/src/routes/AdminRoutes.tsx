// Active Core Modules
import { AdminDashboardPage } from '../pages/admin/dashboard'
import { AdminNotificationsPage } from '../pages/admin/notifications'
import { UnderDevelopmentPage } from '../pages/admin/under-development/UnderDevelopmentPage'

// Active Module: Audit Engagement Management (AEM)
import {
  AdminAuditEvidencePage,
  AdminAuditProgramsPage,
  AdminConferencePage,
  AdminEngagementDetailsPage,
  AdminEngagementsPage,
  AdminExecutionPage,
  AdminFieldworkRecordsPage,
  AdminPrepareAepPage,
  AdminProcedureDetailPage,
  AdminTeamAssignmentPage,
  AdminWorkingPapersPage,
  CreateAuditEngagementPage,
  DefineScopePage,
  PrepareOrderPage,
  PreliminaryMeetingPage,
  UnderstandingAuditeePage,
  UnderstandingProcessPage,
  InitialAssessmentIcsPage,
} from '../pages/admin/audit-engagement'

// Modules currently under development (commented out while focusing on AEM & Dashboard)
/*
import {
  AdminAnnualAuditPlanPage,
  AdminAuditCalendarPage,
} from '../pages/admin/annual-audit-planning'
import { AdminAuditUniversePage } from '../pages/admin/audit-universe'
import { CreateAuditableUnitPage } from '../pages/admin/audit-universe/Pages/CreateAuditableUnitPage'
import { AdminCategoriesPage } from '../pages/admin/categories'
import { AdminCompliancesPage } from '../pages/admin/compliances'
import { AdminDocumentsPage } from '../pages/admin/documents'
import { AdminOfficesPage } from '../pages/admin/offices'
import { AdminAuditAreasPage } from '../pages/admin/audit-areas'
import { AdminAuditFocusesPage } from '../pages/admin/audit-focuses'
import { AdminPoliciesPage } from '../pages/admin/policies'
import { AdminReportsPage } from '../pages/admin/reports'
import {
  AdminAssessmentMatrixPage,
  AdminRiskAssessmentPage,
  AdminRiskRegisterPage,
} from '../pages/admin/risk-assessment'
import { AdminRiskManagementPage } from '../pages/admin/risk-management'
import { AdminSettingsPage } from '../pages/admin/settings'
import { AdminTasksPage } from '../pages/admin/tasks'
import { AdminTrainingPage } from '../pages/admin/training'
import { AdminUsersPage } from '../pages/admin/users'
*/

export const adminRoutes = [
  // --- Active Core Modules (Dashboard & Notifications) ---
  { path: '/', element: <AdminDashboardPage /> },
  { path: '/admin/dashboard', element: <AdminDashboardPage /> },
  { path: '/admin/notifications', element: <AdminNotificationsPage /> },
  { path: '/notifications', element: <AdminNotificationsPage /> },

  // --- Modules Under Active Development (Prioritizing AEM & Dashboard) ---
  {
    path: '/admin/compliances',
    // element: <AdminCompliancesPage />,
    element: <UnderDevelopmentPage title="Compliance Management" moduleCode="CMS" />,
  },
  {
    path: '/admin/categories',
    // element: <AdminCategoriesPage />,
    element: <UnderDevelopmentPage title="Compliance Categories" moduleCode="CMS" />,
  },
  {
    path: '/admin/offices',
    // element: <AdminOfficesPage />,
    element: <UnderDevelopmentPage title="Office Registry" moduleCode="REGISTRY" />,
  },
  {
    path: '/admin/audit-areas',
    // element: <AdminAuditAreasPage />,
    element: <UnderDevelopmentPage title="Audit Area Registry" moduleCode="REGISTRY" />,
  },
  {
    path: '/admin/audit-focuses',
    // element: <AdminAuditFocusesPage />,
    element: <UnderDevelopmentPage title="Audit Focus Registry" moduleCode="REGISTRY" />,
  },
  {
    path: '/admin/documents',
    // element: <AdminDocumentsPage />,
    element: <UnderDevelopmentPage title="Document Management" moduleCode="DOCS" />,
  },
  {
    path: '/admin/policies',
    // element: <AdminPoliciesPage />,
    element: <UnderDevelopmentPage title="Policies & Standards" moduleCode="POLICY" />,
  },
  {
    path: '/admin/risk-management',
    // element: <AdminRiskManagementPage />,
    element: <UnderDevelopmentPage title="Risk Management" moduleCode="RISK" />,
  },
  {
    path: '/admin/risk-assessment',
    // element: <AdminRiskAssessmentPage />,
    element: <UnderDevelopmentPage title="Risk Assessment" moduleCode="RISK" />,
  },
  {
    path: '/admin/risk-assessment/assessment-matrix',
    // element: <AdminAssessmentMatrixPage />,
    element: <UnderDevelopmentPage title="Assessment Matrix" moduleCode="RISK" />,
  },
  {
    path: '/admin/risk-assessment/risk-register',
    // element: <AdminRiskRegisterPage />,
    element: <UnderDevelopmentPage title="Risk Register" moduleCode="RISK" />,
  },
  {
    path: '/admin/annual-audit-plan',
    // element: <AdminAnnualAuditPlanPage />,
    element: <UnderDevelopmentPage title="Internal Audit Planning" moduleCode="IAP" />,
  },
  {
    path: '/admin/audit-calendar',
    // element: <AdminAuditCalendarPage />,
    element: <UnderDevelopmentPage title="Audit Calendar" moduleCode="IAP" />,
  },
  {
    path: '/admin/team-assignment',
    // element: <AdminTeamAssignmentPage />,
    element: <UnderDevelopmentPage title="Audit Resource Management" moduleCode="ARMIS" />,
  },
  {
    path: '/admin/audit-universe',
    // element: <AdminAuditUniversePage />,
    element: <UnderDevelopmentPage title="Auditable Units (Audit Universe)" moduleCode="UNIVERSE" />,
  },
  {
    path: '/admin/audit-universe/create',
    // element: <CreateAuditableUnitPage />,
    element: <UnderDevelopmentPage title="Create Auditable Unit" moduleCode="UNIVERSE" />,
  },
  {
    path: '/admin/reports',
    // element: <AdminReportsPage />,
    element: <UnderDevelopmentPage title="Audit Intelligence System & Reports" moduleCode="AIS" />,
  },
  {
    path: '/admin/settings',
    // element: <AdminSettingsPage />,
    element: <UnderDevelopmentPage title="Settings & Administration" moduleCode="SETTINGS" />,
  },
  {
    path: '/admin/tasks',
    // element: <AdminTasksPage />,
    element: <UnderDevelopmentPage title="Activity Logs & Tasks" moduleCode="TASKS" />,
  },
  {
    path: '/admin/training',
    // element: <AdminTrainingPage />,
    element: <UnderDevelopmentPage title="Auditor Training" moduleCode="TRAINING" />,
  },
  {
    path: '/admin/users',
    // element: <AdminUsersPage />,
    element: <UnderDevelopmentPage title="User Registry" moduleCode="USERS" />,
  },

  // --- Active Core Module: Audit Engagement Management (AEM) ---
  { path: '/admin/audit-engagements', element: <AdminEngagementsPage /> },
  { path: '/admin/audit-engagement', element: <AdminEngagementsPage /> },
  { path: '/admin/audit-engagement/create', element: <CreateAuditEngagementPage /> },
  { path: '/admin/audit-engagement/:id/define-scope', element: <DefineScopePage /> },
  { path: '/admin/audit-engagement/:id/team-assignment', element: <AdminTeamAssignmentPage /> },
  { path: '/admin/audit-engagement/:id/prepare-order', element: <PrepareOrderPage /> },
  { path: '/admin/audit-engagement/:id/planning/preliminary-survey/preliminary-meeting', element: <PreliminaryMeetingPage /> },
  { path: '/admin/audit-engagement/:id/planning/preliminary-meeting', element: <PreliminaryMeetingPage /> },
  { path: '/admin/audit-engagement/:id/preliminary-meeting', element: <PreliminaryMeetingPage /> },
  { path: '/admin/audit-engagement/:id/planning/preliminary-survey/understanding-auditee', element: <UnderstandingAuditeePage /> },
  { path: '/admin/audit-engagement/:id/planning/understanding-auditee', element: <UnderstandingAuditeePage /> },
  { path: '/admin/audit-engagement/:id/understanding-auditee', element: <UnderstandingAuditeePage /> },
  { path: '/admin/audit-engagement/:id/planning/preliminary-survey/understanding-process', element: <UnderstandingProcessPage /> },
  { path: '/admin/audit-engagement/:id/planning/understanding-process', element: <UnderstandingProcessPage /> },
  { path: '/admin/audit-engagement/:id/understanding-process', element: <UnderstandingProcessPage /> },
  { path: '/admin/audit-engagement/:id/planning/preliminary-survey/initial-assessment-ics', element: <InitialAssessmentIcsPage /> },
  { path: '/admin/audit-engagement/:id/planning/initial-assessment-ics', element: <InitialAssessmentIcsPage /> },
  { path: '/admin/audit-engagement/:id/initial-assessment-ics', element: <InitialAssessmentIcsPage /> },
  { path: '/admin/audit-engagement/:id/planning/aep', element: <AdminPrepareAepPage /> },
  { path: '/admin/audit-engagement/:id/aep', element: <AdminPrepareAepPage /> },
  { path: '/admin/audit-engagement/:id/prepare-aep', element: <AdminPrepareAepPage /> },
  { path: '/admin/audit-engagement/:id/planning/programs', element: <AdminAuditProgramsPage /> },
  { path: '/admin/audit-engagement/:id/programs', element: <AdminAuditProgramsPage /> },
  { path: '/admin/audit-engagement/:id/audit-programs', element: <AdminAuditProgramsPage /> },
  { path: '/admin/audit-engagement/:id/planning/procedures/:procedureId', element: <AdminProcedureDetailPage /> },
  { path: '/admin/audit-engagement/:id/procedures/:procedureId', element: <AdminProcedureDetailPage /> },
  { path: '/admin/audit-engagement/:id/conference/entry', element: <AdminConferencePage /> },
  { path: '/admin/audit-engagement/:id/conference/exit', element: <AdminConferencePage /> },
  { path: '/admin/audit-engagement/:id/conference', element: <AdminConferencePage /> },
  { path: '/admin/audit-engagement/:id/conferences', element: <AdminConferencePage /> },
  { path: '/admin/audit-engagement/:id/entry-conference', element: <AdminConferencePage /> },
  { path: '/admin/audit-engagement/:id/execution', element: <AdminExecutionPage /> },
  { path: '/admin/audit-engagement/:id/execution-workspace', element: <AdminExecutionPage /> },
  { path: '/admin/audit-engagement/:id/execution/records', element: <AdminFieldworkRecordsPage /> },
  { path: '/admin/audit-engagement/:id/execution/records/:recordId', element: <AdminFieldworkRecordsPage /> },
  { path: '/admin/audit-engagement/:id/execution/working-papers', element: <AdminWorkingPapersPage /> },
  { path: '/admin/audit-engagement/:id/execution/working-papers/:wpId', element: <AdminWorkingPapersPage /> },
  { path: '/admin/audit-engagement/:id/execution/workpapers', element: <AdminWorkingPapersPage /> },
  { path: '/admin/audit-engagement/:id/execution/workpapers/:wpId', element: <AdminWorkingPapersPage /> },
  { path: '/admin/audit-engagement/:id/execution/evidence', element: <AdminAuditEvidencePage /> },
  { path: '/admin/audit-engagement/:id/execution/evidence/:evidenceId', element: <AdminAuditEvidencePage /> },
  { path: '/admin/audit-engagement/:id/fieldwork-records', element: <AdminFieldworkRecordsPage /> },
  { path: '/admin/audit-engagement/:id/working-papers', element: <AdminWorkingPapersPage /> },
  { path: '/admin/audit-engagement/:id/working-papers/:wpId', element: <AdminWorkingPapersPage /> },
  { path: '/admin/audit-engagement/:id/workpapers', element: <AdminWorkingPapersPage /> },
  { path: '/admin/audit-engagement/:id/workpapers/:wpId', element: <AdminWorkingPapersPage /> },
  { path: '/admin/audit-engagement/:id/evidence', element: <AdminAuditEvidencePage /> },
  { path: '/admin/audit-engagement/:id/evidence/:evidenceId', element: <AdminAuditEvidencePage /> },
  { path: '/admin/audit-engagement/:id', element: <AdminEngagementDetailsPage /> },
  { path: '/admin/audit-engagement/:id/:tab', element: <AdminEngagementDetailsPage /> },
  { path: '/admin/audit-engagements/:id', element: <AdminEngagementDetailsPage /> },
  { path: '/admin/audit-engagements/:id/:tab', element: <AdminEngagementDetailsPage /> },
]
