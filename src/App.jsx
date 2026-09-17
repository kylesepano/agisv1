import { lazy, Suspense } from "react";
import { Navigate, Route, Routes } from "react-router";
import AuthProvider from "./auth/AuthProvider";
import AppLayout from "./layouts/AppLayout";
import LoginPage from "./pages/shared/LoginPage";
import ModulePage from "./pages/shared/ModulePage";
import UnauthorizedPage from "./pages/shared/UnauthorizedPage";
import { pageRoutes } from "./config/navigation";
import ToastProvider from "./ui/ToastProvider";
import {
  PublicOnly,
  RequireAuth,
  RequirePermission,
} from "./routes/guards";

const DashboardPage = lazy(() => import("./pages/core/DashboardPage"));
const OfficeRegistryPage = lazy(() => import("./pages/core/OfficeRegistryPage"));
const AuditAreaRegistryPage = lazy(
  () => import("./pages/core/AuditAreaRegistryPage"),
);
const AuditFocusRegistryPage = lazy(
  () => import("./pages/core/AuditFocusRegistryPage"),
);
const UserRegistryPage = lazy(() => import("./pages/core/UserRegistryPage"));
const AccessControlRegistryPage = lazy(
  () => import("./pages/core/AccessControlRegistryPage"),
);
const MasterListsPage = lazy(() => import("./pages/core/MasterListsPage"));
const SystemConfigurationPage = lazy(
  () => import("./pages/core/SystemConfigurationPage"),
);
const AdministrativeReportsPage = lazy(
  () => import("./pages/core/AdministrativeReportsPage"),
);
const ActivityLogPage = lazy(() => import("./pages/core/ActivityLogPage"));
const AuditTrailPage = lazy(() => import("./pages/core/AuditTrailPage"));
const DocumentManagementPage = lazy(
  () => import("./pages/core/DocumentManagementPage"),
);
const WorkflowManagementPage = lazy(
  () => import("./pages/core/WorkflowManagementPage"),
);
const NotificationCenterPage = lazy(
  () => import("./pages/core/NotificationCenterPage"),
);
const IapPlanRegistryPage = lazy(
  () => import("./pages/iap/IapPlanRegistryPage"),
);
const IapDashboardPage = lazy(() => import("./pages/iap/IapDashboardPage"));
const SiapPlanRegistryPage = lazy(
  () => import("./pages/iap/SiapPlanRegistryPage"),
);
const IapAuditUniversePage = lazy(
  () => import("./pages/iap/IapAuditUniversePage"),
);
const IapBaicsPage = lazy(() => import("./pages/iap/IapBaicsPage"));
const IapBaicsControlUniversePage = lazy(
  () => import("./pages/iap/IapBaicsControlUniversePage"),
);
const IapBaicsIntegrationPage = lazy(
  () => import("./pages/iap/IapBaicsIntegrationPage"),
);
const IapRiskAssessmentPeriodsPage = lazy(
  () => import("./pages/iap/IapRiskAssessmentPeriodsPage"),
);
const IapPrioritizationPage = lazy(
  () => import("./pages/iap/IapPrioritizationPage"),
);
const IapPlanWorkspacePage = lazy(
  () => import("./pages/iap/IapPlanWorkspacePage"),
);
const IapSchedulingPage = lazy(() => import("./pages/iap/IapSchedulingPage"));
const IapReportsPage = lazy(() => import("./pages/iap/IapReportsPage"));
const AemsEngagementRegistryPage = lazy(
  () => import("./pages/aems/AemsEngagementRegistryPage"),
);
const AemsEngagementScopePage = lazy(
  () => import("./pages/aems/AemsEngagementScopePage"),
);
const AemsDashboardPage = lazy(() => import("./pages/aems/AemsDashboardPage"));
const AemsTeamPage = lazy(() => import("./pages/aems/AemsTeamPage"));
const AemsAeoPage = lazy(() => import("./pages/aems/AemsAeoPage"));
const AemsAepPage = lazy(() => import("./pages/aems/AemsAepPage"));
const AemsPlanningPackagePage = lazy(
  () => import("./pages/aems/AemsPlanningPackagePage"),
);
const AemsAuditProgramPage = lazy(
  () => import("./pages/aems/AemsAuditProgramPage"),
);
const AemsExecutionWorkspacePage = lazy(
  () => import("./pages/aems/AemsExecutionWorkspacePage"),
);
const AemsWorkingPapersPage = lazy(
  () => import("./pages/aems/AemsWorkingPapersPage"),
);
const AemsEvidenceManagementPage = lazy(
  () => import("./pages/aems/AemsEvidenceManagementPage"),
);
const AemsFindingsPage = lazy(() => import("./pages/aems/AemsFindingsPage"));
const AemsIssuesPage = lazy(() => import("./pages/aems/AemsIssuesPage"));
const AemsResponsesPage = lazy(() => import("./pages/aems/AemsResponsesPage"));
const AemsExitConferencesPage = lazy(
  () => import("./pages/aems/AemsExitConferencesPage"),
);
const AemsConferenceDialoguePage = lazy(
  () => import("./pages/aems/AemsConferenceDialoguePage"),
);
const AemsReportsPage = lazy(() => import("./pages/aems/AemsReportsPage"));
const AemsEngagementDetailPage = lazy(
  () => import("./pages/aems/AemsEngagementDetailPage"),
);
const AemsEntryConferencePage = lazy(
  () => import("./pages/aems/AemsEntryConferencePage"),
);
const AemsOperationalQueuesPage = lazy(
  () => import("./pages/aems/AemsOperationalQueuesPage"),
);
const AemsCalendarPage = lazy(() => import("./pages/aems/AemsCalendarPage"));
const AemsRegistersPage = lazy(() => import("./pages/aems/AemsRegistersPage"));
const AemsRecordsClosurePage = lazy(
  () => import("./pages/aems/AemsRecordsClosurePage"),
);
const CmsDashboardPage = lazy(() => import("./pages/cms/CmsDashboardPage"));
const CmsRecommendationRegistryPage = lazy(
  () => import("./pages/cms/CmsRecommendationRegistryPage"),
);
const CmsAeoAcknowledgementsPage = lazy(
  () => import("./pages/cms/CmsAeoAcknowledgementsPage"),
);
const CmsEntryConferenceAcknowledgementsPage = lazy(
  () => import("./pages/cms/CmsEntryConferenceAcknowledgementsPage"),
);
const CmsEvidenceRequestsPage = lazy(
  () => import("./pages/cms/CmsEvidenceRequestsPage"),
);
const CmsRecommendationDetailPage = lazy(
  () => import("./pages/cms/CmsRecommendationDetailPage"),
);
const CmsActionPlanPage = lazy(
  () => import("./pages/cms/CmsActionPlanPage"),
);
const CmsProgressUpdatesPage = lazy(
  () => import("./pages/cms/CmsProgressUpdatesPage"),
);
const CmsValidationsPage = lazy(
  () => import("./pages/cms/CmsValidationsPage"),
);
const CmsExtensionsPage = lazy(() => import("./pages/cms/CmsExtensionsPage"));
const CmsEscalationsPage = lazy(() => import("./pages/cms/CmsEscalationsPage"));
const CmsClosureRequestsPage = lazy(() => import("./pages/cms/CmsClosureRequestsPage"));
const CmsDispositionsPage = lazy(() => import("./pages/cms/CmsDispositionsPage"));
const CmsReopeningPage = lazy(() => import("./pages/cms/CmsReopeningPage"));
const CmsAutomationPage = lazy(() => import("./pages/cms/CmsAutomationPage"));
const CmsReportsPage = lazy(() => import("./pages/cms/CmsReportsPage"));
const ArmisResourceRegistryPage = lazy(
  () => import("./pages/armis/ArmisResourceRegistryPage"),
);
const ArmisCompetencyPage = lazy(
  () => import("./pages/armis/ArmisCompetencyPage"),
);
const ArmisPlanningPage = lazy(() => import("./pages/armis/ArmisPlanningPage"));
const ArmisAssignmentsPage = lazy(
  () => import("./pages/armis/ArmisAssignmentsPage"),
);
const ArmisReportsPage = lazy(() => import("./pages/armis/ArmisReportsPage"));
const ArmisProviderMonitoringPage = lazy(
  () => import("./pages/armis/ArmisProviderMonitoringPage"),
);
const ProfilePage = lazy(() => import("./pages/core/ProfilePage"));
const AisGovernancePage = lazy(() => import("./pages/ais/AisGovernancePage"));
const AisIntegrationHealthPage = lazy(() => import("./pages/ais/AisIntegrationHealthPage"));
const AisReportsPage = lazy(() => import("./pages/ais/AisReportsPage"));

const implementedCorePaths = new Set([
  "/office-registry",
  "/audit-area-registry",
  "/audit-focus-registry",
  "/user-registry",
  "/access-role-registry",
  "/permission-registry",
  "/master-lists",
  "/activity-log",
  "/audit-trail",
  "/document-management",
  "/workflow-management",
  "/notifications",
  "/system-configuration",
  "/administrative-reports",
  "/audit-intelligence-system",
  "/audit-intelligence-system/integration-health",
  "/audit-intelligence-system/reports",
  "/internal-audit-planning",
  "/internal-audit-planning/dashboard",
  "/internal-audit-planning/strategic-plan",
  "/internal-audit-planning/audit-universe",
  "/internal-audit-planning/baics",
  "/internal-audit-planning/baics/control-universe",
  "/internal-audit-planning/baics/integration",
  "/internal-audit-planning/baics/reports",
  "/internal-audit-planning/risk-assessment",
  "/internal-audit-planning/prioritization",
  "/internal-audit-planning/scheduling",
  "/internal-audit-planning/reports",
  "/audit-engagement-management",
  "/audit-engagement-management/scope",
  "/audit-engagement-management/dashboard",
  "/audit-engagement-management/team",
  "/audit-engagement-management/aeo",
  "/audit-engagement-management/aep",
  "/audit-engagement-management/planning-package",
  "/audit-engagement-management/audit-program",
  "/audit-engagement-management/audit-procedure-details",
  "/audit-engagement-management/execution",
  "/audit-engagement-management/entry-conferences",
  "/audit-engagement-management/conferences",
  "/audit-engagement-management/working-papers",
  "/audit-engagement-management/evidence",
  "/audit-engagement-management/issues",
  "/audit-engagement-management/findings",
  "/audit-engagement-management/auditee-responses",
  "/audit-engagement-management/exit-conferences",
  "/audit-engagement-management/reports",
  "/audit-engagement-management/work-queues",
  "/audit-engagement-management/calendar",
  "/audit-engagement-management/registers",
  "/audit-engagement-management/records-closure",
  "/compliance-management",
  "/compliance-management/dashboard",
  "/compliance-management/recommendations",
  "/compliance-management/aeo-acknowledgements",
  "/compliance-management/entry-conference-acknowledgements",
  "/compliance-management/automation",
  "/compliance-management/reports",
  "/audit-resource-management",
  "/audit-resource-management/resources",
  "/audit-resource-management/competencies",
  "/audit-resource-management/planning",
  "/audit-resource-management/assignments",
  "/audit-resource-management/reports",
  "/audit-resource-management/provider-monitoring",
]);

function RouteLoading() {
  return (
    <div className="grid min-h-[calc(100vh-6rem)] place-items-center">
      <div className="flex items-center gap-3 text-sm font-semibold text-slate-500">
        <span className="h-6 w-6 animate-spin rounded-full border-2 border-slate-200 border-t-sky-600" />
        Loading page...
      </div>
    </div>
  );
}

function ProtectedPage({ permission, children }) {
  return (
    <RequirePermission permission={permission}>{children}</RequirePermission>
  );
}

export default function App() {
  return (
    <AuthProvider>
      <ToastProvider>
        <Routes>
        <Route
          path="/login"
          element={
            <PublicOnly>
              <LoginPage />
            </PublicOnly>
          }
        />

        <Route
          path="/"
          element={
            <RequireAuth>
              <AppLayout />
            </RequireAuth>
          }
        >
          <Route index element={<Navigate to="/dashboard" replace />} />
          <Route
            path="dashboard"
            element={
              <ProtectedPage permission="dashboard.view">
                <Suspense fallback={<RouteLoading />}>
                  <DashboardPage />
                </Suspense>
              </ProtectedPage>
            }
          />

          <Route
            path="office-registry"
            element={
              <ProtectedPage permission="offices.view">
                <Suspense fallback={<RouteLoading />}>
                  <OfficeRegistryPage />
                </Suspense>
              </ProtectedPage>
            }
          />
          <Route
            path="audit-area-registry"
            element={
              <ProtectedPage permission="audit_areas.view">
                <Suspense fallback={<RouteLoading />}>
                  <AuditAreaRegistryPage />
                </Suspense>
              </ProtectedPage>
            }
          />
          <Route
            path="audit-focus-registry"
            element={
              <ProtectedPage permission="audit_focus.view">
                <Suspense fallback={<RouteLoading />}>
                  <AuditFocusRegistryPage />
                </Suspense>
              </ProtectedPage>
            }
          />
          <Route
            path="user-registry"
            element={
              <ProtectedPage permission="users.view">
                <Suspense fallback={<RouteLoading />}>
                  <UserRegistryPage />
                </Suspense>
              </ProtectedPage>
            }
          />
          <Route
            path="access-role-registry"
            element={
              <ProtectedPage permission="roles.view">
                <Suspense fallback={<RouteLoading />}>
                  <AccessControlRegistryPage mode="roles" />
                </Suspense>
              </ProtectedPage>
            }
          />
          <Route
            path="permission-registry"
            element={
              <ProtectedPage permission="permissions.view">
                <Suspense fallback={<RouteLoading />}>
                  <AccessControlRegistryPage mode="permissions" />
                </Suspense>
              </ProtectedPage>
            }
          />
          <Route
            path="master-lists"
            element={
              <ProtectedPage permission="master_lists.view">
                <Suspense fallback={<RouteLoading />}>
                  <MasterListsPage />
                </Suspense>
              </ProtectedPage>
            }
          />
          <Route
            path="activity-log"
            element={
              <ProtectedPage permission="activity_logs.view">
                <Suspense fallback={<RouteLoading />}>
                  <ActivityLogPage />
                </Suspense>
              </ProtectedPage>
            }
          />
          <Route
            path="audit-trail"
            element={
              <ProtectedPage permission="audit_logs.view">
                <Suspense fallback={<RouteLoading />}>
                  <AuditTrailPage />
                </Suspense>
              </ProtectedPage>
            }
          />
          <Route
            path="document-management"
            element={
              <ProtectedPage permission="documents.view">
                <Suspense fallback={<RouteLoading />}>
                  <DocumentManagementPage />
                </Suspense>
              </ProtectedPage>
            }
          />
          <Route
            path="workflow-management"
            element={
              <ProtectedPage permission="workflows.view">
                <Suspense fallback={<RouteLoading />}>
                  <WorkflowManagementPage />
                </Suspense>
              </ProtectedPage>
            }
          />
          <Route
            path="notifications"
            element={
              <ProtectedPage permission="notifications.view">
                <Suspense fallback={<RouteLoading />}>
                  <NotificationCenterPage />
                </Suspense>
              </ProtectedPage>
            }
          />
          <Route
            path="administrative-reports"
            element={
              <ProtectedPage permission="administrative_reports.view">
                <Suspense fallback={<RouteLoading />}>
                  <AdministrativeReportsPage />
                </Suspense>
              </ProtectedPage>
            }
          />
          <Route
            path="internal-audit-planning/dashboard"
            element={
              <ProtectedPage permission="iap.view">
                <Suspense fallback={<RouteLoading />}>
                  <IapDashboardPage />
                </Suspense>
              </ProtectedPage>
            }
          />
          <Route
            path="internal-audit-planning/strategic-plan"
            element={
              <ProtectedPage permission="iap.view">
                <Suspense fallback={<RouteLoading />}>
                  <SiapPlanRegistryPage />
                </Suspense>
              </ProtectedPage>
            }
          />
          <Route
            path="internal-audit-planning"
            element={
              <ProtectedPage permission="iap.view">
                <Suspense fallback={<RouteLoading />}>
                  <IapPlanRegistryPage />
                </Suspense>
              </ProtectedPage>
            }
          />
          <Route
            path="internal-audit-planning/audit-universe"
            element={
              <ProtectedPage permission="iap.view">
                <Suspense fallback={<RouteLoading />}>
                  <IapAuditUniversePage />
                </Suspense>
              </ProtectedPage>
            }
          />
          <Route
            path="internal-audit-planning/baics"
            element={
              <ProtectedPage permission="iap.baics.view">
                <Suspense fallback={<RouteLoading />}>
                  <IapBaicsPage />
                </Suspense>
              </ProtectedPage>
            }
          />
          <Route
            path="internal-audit-planning/baics/control-universe"
            element={
              <ProtectedPage permission="iap.baics.view">
                <Suspense fallback={<RouteLoading />}>
                  <IapBaicsControlUniversePage />
                </Suspense>
              </ProtectedPage>
            }
          />
          <Route
            path="internal-audit-planning/baics/integration"
            element={
              <ProtectedPage permission="iap.baics.view">
                <Suspense fallback={<RouteLoading />}>
                  <IapBaicsIntegrationPage />
                </Suspense>
              </ProtectedPage>
            }
          />
          <Route
            path="internal-audit-planning/baics/reports"
            element={
              <ProtectedPage permission="iap.baics.view">
                <Suspense fallback={<RouteLoading />}>
                  <IapBaicsControlUniversePage />
                </Suspense>
              </ProtectedPage>
            }
          />
          <Route
            path="internal-audit-planning/risk-assessment"
            element={
              <ProtectedPage permission="iap.view">
                <Suspense fallback={<RouteLoading />}>
                  <IapRiskAssessmentPeriodsPage />
                </Suspense>
              </ProtectedPage>
            }
          />
          <Route
            path="internal-audit-planning/prioritization"
            element={
              <ProtectedPage permission="iap.view">
                <Suspense fallback={<RouteLoading />}>
                  <IapPrioritizationPage />
                </Suspense>
              </ProtectedPage>
            }
          />
          <Route
            path="internal-audit-planning/scheduling"
            element={
              <ProtectedPage permission="iap.view">
                <Suspense fallback={<RouteLoading />}>
                  <IapSchedulingPage />
                </Suspense>
              </ProtectedPage>
            }
          />
          <Route
            path="internal-audit-planning/resource-capacity"
            element={<Navigate to="/audit-resource-management/planning" replace />}
          />
          <Route
            path="internal-audit-planning/reports"
            element={
              <ProtectedPage permission="iap.view">
                <Suspense fallback={<RouteLoading />}>
                  <IapReportsPage />
                </Suspense>
              </ProtectedPage>
            }
          />
          <Route
            path="internal-audit-planning/:planId"
            element={
              <ProtectedPage permission="iap.view">
                <Suspense fallback={<RouteLoading />}>
                  <IapPlanWorkspacePage />
                </Suspense>
              </ProtectedPage>
            }
          />
          <Route
            path="audit-engagement-management/dashboard"
            element={
              <ProtectedPage permission="aems.engagement.view">
                <Suspense fallback={<RouteLoading />}>
                  <AemsDashboardPage />
                </Suspense>
              </ProtectedPage>
            }
          />
          <Route
            path="audit-engagement-management/team"
            element={
              <ProtectedPage permission="aems.team.view">
                <Suspense fallback={<RouteLoading />}>
                  <AemsTeamPage />
                </Suspense>
              </ProtectedPage>
            }
          />
          <Route
            path="audit-engagement-management/aeo"
            element={
              <ProtectedPage permission="aems.aeo.view">
                <Suspense fallback={<RouteLoading />}>
                  <AemsAeoPage />
                </Suspense>
              </ProtectedPage>
            }
          />
          <Route
            path="audit-engagement-management/aep"
            element={
              <ProtectedPage permission="aems.aep.view">
                <Suspense fallback={<RouteLoading />}>
                  <AemsAepPage />
                </Suspense>
              </ProtectedPage>
            }
          />
          <Route
            path="audit-engagement-management/planning-package"
            element={
              <ProtectedPage permission="aems.planning-package.view">
                <Suspense fallback={<RouteLoading />}>
                  <AemsPlanningPackagePage />
                </Suspense>
              </ProtectedPage>
            }
          />
          <Route
            path="audit-engagement-management/audit-program"
            element={
              <ProtectedPage permission="aems.program.view">
                <Suspense fallback={<RouteLoading />}>
                  <AemsAuditProgramPage />
                </Suspense>
              </ProtectedPage>
            }
          />
          <Route
            path="audit-engagement-management/audit-procedure-details"
            element={
              <ProtectedPage permission="aems.program.view">
                <Suspense fallback={<RouteLoading />}>
                  <AemsAuditProgramPage />
                </Suspense>
              </ProtectedPage>
            }
          />
          <Route
            path="audit-engagement-management/execution"
            element={
              <ProtectedPage permission="aems.fieldwork.view">
                <Suspense fallback={<RouteLoading />}>
                  <AemsExecutionWorkspacePage />
                </Suspense>
              </ProtectedPage>
            }
          />
          <Route
            path="audit-engagement-management/working-papers"
            element={
              <ProtectedPage permission="aems.working-paper.view">
                <Suspense fallback={<RouteLoading />}>
                  <AemsWorkingPapersPage />
                </Suspense>
              </ProtectedPage>
            }
          />
          <Route
            path="audit-engagement-management/evidence"
            element={
              <ProtectedPage permission="aems.evidence-request.view">
                <Suspense fallback={<RouteLoading />}>
                  <AemsEvidenceManagementPage />
                </Suspense>
              </ProtectedPage>
            }
          />
          <Route
            path="audit-engagement-management/issues"
            element={
              <ProtectedPage permission="aems.issue.view">
                <Suspense fallback={<RouteLoading />}>
                  <AemsIssuesPage />
                </Suspense>
              </ProtectedPage>
            }
          />
          <Route
            path="audit-engagement-management/findings"
            element={
              <ProtectedPage permission="aems.finding.view">
                <Suspense fallback={<RouteLoading />}>
                  <AemsFindingsPage />
                </Suspense>
              </ProtectedPage>
            }
          />
          <Route
            path="audit-engagement-management/auditee-responses"
            element={
              <ProtectedPage permission="aems.management-response.view">
                <Suspense fallback={<RouteLoading />}>
                  <AemsResponsesPage />
                </Suspense>
              </ProtectedPage>
            }
          />
          <Route
            path="audit-engagement-management/exit-conferences"
            element={
              <ProtectedPage permission="aems.conference.view">
                <Suspense fallback={<RouteLoading />}>
                  <AemsExitConferencesPage />
                </Suspense>
              </ProtectedPage>
            }
          />
          <Route
            path="audit-engagement-management/conferences"
            element={
              <ProtectedPage permission="aems.conference.view">
                <Suspense fallback={<RouteLoading />}>
                  <AemsConferenceDialoguePage />
                </Suspense>
              </ProtectedPage>
            }
          />
          <Route
            path="audit-engagement-management/reports"
            element={
              <ProtectedPage
                permission={["aems.report.view", "aems.report.view_issued"]}
              >
                <Suspense fallback={<RouteLoading />}>
                  <AemsReportsPage />
                </Suspense>
              </ProtectedPage>
            }
          />
          <Route
            path="audit-engagement-management/work-queues"
            element={
              <ProtectedPage permission="aems.task.view">
                <Suspense fallback={<RouteLoading />}>
                  <AemsOperationalQueuesPage />
                </Suspense>
              </ProtectedPage>
            }
          />
          <Route
            path="audit-engagement-management/calendar"
            element={
              <ProtectedPage permission="aems.calendar.view">
                <Suspense fallback={<RouteLoading />}>
                  <AemsCalendarPage />
                </Suspense>
              </ProtectedPage>
            }
          />
          <Route
            path="audit-engagement-management/registers"
            element={
              <ProtectedPage permission="aems.engagement.view">
                <Suspense fallback={<RouteLoading />}>
                  <AemsRegistersPage />
                </Suspense>
              </ProtectedPage>
            }
          />
          <Route
            path="audit-engagement-management/records-closure"
            element={
              <ProtectedPage
                permission={[
                  "aems.closure.view",
                  "aems.records.view",
                  "aems.retention.view",
                ]}
              >
                <Suspense fallback={<RouteLoading />}>
                  <AemsRecordsClosurePage />
                </Suspense>
              </ProtectedPage>
            }
          />
          <Route
            path="audit-engagement-management"
            element={
              <ProtectedPage permission="aems.engagement.view">
                <Suspense fallback={<RouteLoading />}>
                  <AemsEngagementRegistryPage />
                </Suspense>
              </ProtectedPage>
            }
          />
          <Route
            path="audit-engagement-management/scope"
            element={
              <ProtectedPage permission="aems.foundation.view">
                <Suspense fallback={<RouteLoading />}>
                  <AemsEngagementScopePage />
                </Suspense>
              </ProtectedPage>
            }
          />
          <Route
            path="audit-engagement-management/entry-conferences"
            element={
              <ProtectedPage permission="aems.entry-conference.view">
                <Suspense fallback={<RouteLoading />}>
                  <AemsEntryConferencePage />
                </Suspense>
              </ProtectedPage>
            }
          />
          <Route
            path="audit-engagement-management/entry-conference/:engagementId"
            element={
              <ProtectedPage permission="aems.entry-conference.view">
                <Suspense fallback={<RouteLoading />}>
                  <AemsEntryConferencePage />
                </Suspense>
              </ProtectedPage>
            }
          />
          <Route
            path="audit-engagement-management/:engagementId"
            element={
              <ProtectedPage permission="aems.engagement.view">
                <Suspense fallback={<RouteLoading />}>
                  <AemsEngagementDetailPage />
                </Suspense>
              </ProtectedPage>
            }
          />
          <Route
            path="compliance-management"
            element={
              <ProtectedPage permission="cms.dashboard.view">
                <Navigate to="/compliance-management/dashboard" replace />
              </ProtectedPage>
            }
          />
          <Route
            path="compliance-management/dashboard"
            element={
              <ProtectedPage permission="cms.dashboard.view">
                <Suspense fallback={<RouteLoading />}>
                  <CmsDashboardPage />
                </Suspense>
              </ProtectedPage>
            }
          />
          <Route
            path="compliance-management/recommendations"
            element={
              <ProtectedPage permission="cms.recommendation.view">
                <Suspense fallback={<RouteLoading />}>
                  <CmsRecommendationRegistryPage />
                </Suspense>
              </ProtectedPage>
            }
          />
          <Route
            path="compliance-management/aeo-acknowledgements"
            element={
              <ProtectedPage permission="aems.aeo.acknowledge">
                <Suspense fallback={<RouteLoading />}>
                  <CmsAeoAcknowledgementsPage />
                </Suspense>
              </ProtectedPage>
            }
          />
          <Route
            path="compliance-management/entry-conference-acknowledgements"
            element={
              <ProtectedPage permission="aems.entry-conference.view">
                <Suspense fallback={<RouteLoading />}>
                  <CmsEntryConferenceAcknowledgementsPage />
                </Suspense>
              </ProtectedPage>
            }
          />
          <Route
            path="compliance-management/evidence-requests"
            element={
              <ProtectedPage permission="aems.evidence-request.view">
                <Suspense fallback={<RouteLoading />}>
                  <CmsEvidenceRequestsPage />
                </Suspense>
              </ProtectedPage>
            }
          />
          <Route
            path="compliance-management/automation"
            element={
              <ProtectedPage permission="cms.automation.view">
                <Suspense fallback={<RouteLoading />}>
                  <CmsAutomationPage />
                </Suspense>
              </ProtectedPage>
            }
          />
          <Route
            path="compliance-management/reports"
            element={
              <ProtectedPage permission="cms.report.view">
                <Suspense fallback={<RouteLoading />}>
                  <CmsReportsPage />
                </Suspense>
              </ProtectedPage>
            }
          />
          <Route
            path="compliance-management/recommendations/:recommendationId"
            element={
              <ProtectedPage permission="cms.recommendation.view">
                <Suspense fallback={<RouteLoading />}>
                  <CmsRecommendationDetailPage />
                </Suspense>
              </ProtectedPage>
            }
          />
          <Route
            path="compliance-management/recommendations/:recommendationId/action-plan"
            element={
              <ProtectedPage permission="cms.action-plan.view">
                <Suspense fallback={<RouteLoading />}>
                  <CmsActionPlanPage />
                </Suspense>
              </ProtectedPage>
            }
          />
          <Route
            path="compliance-management/recommendations/:recommendationId/progress-updates"
            element={
              <ProtectedPage permission="cms.progress.view">
                <Suspense fallback={<RouteLoading />}>
                  <CmsProgressUpdatesPage />
                </Suspense>
              </ProtectedPage>
            }
          />
          <Route
            path="compliance-management/recommendations/:recommendationId/progress-updates/:progressUpdateId"
            element={
              <ProtectedPage permission="cms.progress.view">
                <Suspense fallback={<RouteLoading />}>
                  <CmsProgressUpdatesPage />
                </Suspense>
              </ProtectedPage>
            }
          />
          <Route
            path="compliance-management/recommendations/:recommendationId/validations"
            element={
              <ProtectedPage permission="cms.validation.view">
                <Suspense fallback={<RouteLoading />}>
                  <CmsValidationsPage />
                </Suspense>
              </ProtectedPage>
            }
          />
          <Route
            path="compliance-management/recommendations/:recommendationId/validations/:validationId"
            element={
              <ProtectedPage permission="cms.validation.view">
                <Suspense fallback={<RouteLoading />}>
                  <CmsValidationsPage />
                </Suspense>
              </ProtectedPage>
            }
          />
          <Route
            path="compliance-management/recommendations/:recommendationId/extensions"
            element={
              <ProtectedPage permission="cms.extension.view">
                <Suspense fallback={<RouteLoading />}>
                  <CmsExtensionsPage />
                </Suspense>
              </ProtectedPage>
            }
          />
          <Route
            path="compliance-management/recommendations/:recommendationId/extensions/:extensionId"
            element={
              <ProtectedPage permission="cms.extension.view">
                <Suspense fallback={<RouteLoading />}>
                  <CmsExtensionsPage />
                </Suspense>
              </ProtectedPage>
            }
          />
          <Route
            path="compliance-management/recommendations/:recommendationId/escalations"
            element={
              <ProtectedPage permission="cms.escalation.view">
                <Suspense fallback={<RouteLoading />}>
                  <CmsEscalationsPage />
                </Suspense>
              </ProtectedPage>
            }
          />
          <Route
            path="compliance-management/recommendations/:recommendationId/escalations/:escalationId"
            element={
              <ProtectedPage permission="cms.escalation.view">
                <Suspense fallback={<RouteLoading />}>
                  <CmsEscalationsPage />
                </Suspense>
              </ProtectedPage>
            }
          />
          <Route
            path="compliance-management/recommendations/:recommendationId/closure-requests"
            element={
              <ProtectedPage permission="cms.closure.view">
                <Suspense fallback={<RouteLoading />}>
                  <CmsClosureRequestsPage />
                </Suspense>
              </ProtectedPage>
            }
          />
          <Route
            path="compliance-management/recommendations/:recommendationId/closure-requests/:closureRequestId"
            element={
              <ProtectedPage permission="cms.closure.view">
                <Suspense fallback={<RouteLoading />}>
                  <CmsClosureRequestsPage />
                </Suspense>
              </ProtectedPage>
            }
          />
          <Route
            path="compliance-management/recommendations/:recommendationId/dispositions"
            element={
              <ProtectedPage permission="cms.disposition.view">
                <Suspense fallback={<RouteLoading />}>
                  <CmsDispositionsPage />
                </Suspense>
              </ProtectedPage>
            }
          />
          <Route
            path="compliance-management/recommendations/:recommendationId/dispositions/:dispositionId"
            element={
              <ProtectedPage permission="cms.disposition.view">
                <Suspense fallback={<RouteLoading />}>
                  <CmsDispositionsPage />
                </Suspense>
              </ProtectedPage>
            }
          />
          <Route
            path="compliance-management/recommendations/:recommendationId/reopening-requests"
            element={
              <ProtectedPage permission="cms.reopening.view">
                <Suspense fallback={<RouteLoading />}>
                  <CmsReopeningPage />
                </Suspense>
              </ProtectedPage>
            }
          />
          <Route
            path="compliance-management/recommendations/:recommendationId/reopening-requests/:reopeningRequestId"
            element={
              <ProtectedPage permission="cms.reopening.view">
                <Suspense fallback={<RouteLoading />}>
                  <CmsReopeningPage />
                </Suspense>
              </ProtectedPage>
            }
          />
          <Route
            path="audit-resource-management/competencies"
            element={
              <ProtectedPage permission="armis.competency.view">
                <Suspense fallback={<RouteLoading />}>
                  <ArmisCompetencyPage />
                </Suspense>
              </ProtectedPage>
            }
          />
          <Route
            path="audit-resource-management/competencies/:competencyId"
            element={
              <ProtectedPage permission="armis.competency.view">
                <Suspense fallback={<RouteLoading />}>
                  <ArmisCompetencyPage />
                </Suspense>
              </ProtectedPage>
            }
          />
          <Route
            path="audit-resource-management/planning"
            element={
              <ProtectedPage permission="armis.availability.view">
                <Suspense fallback={<RouteLoading />}>
                  <ArmisPlanningPage />
                </Suspense>
              </ProtectedPage>
            }
          />
          <Route
            path="audit-resource-management/assignments"
            element={
              <ProtectedPage permission="armis.assignment.view">
                <Suspense fallback={<RouteLoading />}>
                  <ArmisAssignmentsPage />
                </Suspense>
              </ProtectedPage>
            }
          />
          <Route
            path="audit-resource-management/reports"
            element={
              <ProtectedPage permission="armis.report.view">
                <Suspense fallback={<RouteLoading />}>
                  <ArmisReportsPage />
                </Suspense>
              </ProtectedPage>
            }
          />
          <Route
            path="audit-resource-management/provider-reconciliation"
            element={<Navigate to="/audit-resource-management/provider-monitoring" replace />}
          />
          <Route
            path="audit-resource-management/provider-monitoring"
            element={
              <ProtectedPage permission="armis.provider.view">
                <Suspense fallback={<RouteLoading />}>
                  <ArmisProviderMonitoringPage />
                </Suspense>
              </ProtectedPage>
            }
          />
          <Route
            path="audit-resource-management/resources"
            element={
              <ProtectedPage permission="armis.resource.view">
                <Suspense fallback={<RouteLoading />}>
                  <ArmisResourceRegistryPage />
                </Suspense>
              </ProtectedPage>
            }
          />
          <Route
            path="audit-resource-management"
            element={
              <ProtectedPage permission="armis.resource.view">
                <Navigate to="/audit-resource-management/resources" replace />
              </ProtectedPage>
            }
          />
          <Route
            path="audit-resource-management/resources/:profileId"
            element={
              <ProtectedPage permission="armis.resource.view">
                <Suspense fallback={<RouteLoading />}>
                  <ArmisResourceRegistryPage />
                </Suspense>
              </ProtectedPage>
            }
          />
          <Route
            path="system-configuration"
            element={
              <ProtectedPage permission="system_configuration.view">
                <Suspense fallback={<RouteLoading />}>
                  <SystemConfigurationPage />
                </Suspense>
              </ProtectedPage>
            }
          />
          <Route
            path="profile"
            element={
              <ProtectedPage permission="profile.view">
                <Suspense fallback={<RouteLoading />}>
                  <ProfilePage />
                </Suspense>
              </ProtectedPage>
            }
          />
          <Route
            path="audit-intelligence-system"
            element={
              <ProtectedPage permission="ais.view">
                <Suspense fallback={<RouteLoading />}>
                  <AisGovernancePage />
                </Suspense>
              </ProtectedPage>
            }
          />
          <Route
            path="audit-intelligence-system/integration-health"
            element={
              <ProtectedPage permission="ais.view">
                <Suspense fallback={<RouteLoading />}>
                  <AisIntegrationHealthPage />
                </Suspense>
              </ProtectedPage>
            }
          />
          <Route
            path="audit-intelligence-system/reports"
            element={
              <ProtectedPage permission="ais.view">
                <Suspense fallback={<RouteLoading />}>
                  <AisReportsPage />
                </Suspense>
              </ProtectedPage>
            }
          />

          {pageRoutes
            .filter((page) => !implementedCorePaths.has(page.path))
            .map((page) => (
            <Route
              key={page.path}
              path={page.path.slice(1)}
              element={
                <ProtectedPage permission={page.permission}>
                  <ModulePage />
                </ProtectedPage>
              }
            />
            ))}

          <Route path="unauthorized" element={<UnauthorizedPage />} />
          <Route path="*" element={<Navigate to="/dashboard" replace />} />
        </Route>

        <Route path="*" element={<Navigate to="/login" replace />} />
        </Routes>
      </ToastProvider>
    </AuthProvider>
  );
}
