import RoleSwitcher from './RoleSwitcher';
import NotificationBell from './NotificationBell';
import NotificationHistoryPage from './NotificationHistoryPage';
import ThemeToggle from './ThemeToggle';
import TicketWorkspace from './TicketWorkspace';
import AgentsPanel from './AgentsPanel';
import SlaChart from './SlaChart';
import CategoryChart from './CategoryChart';
import KnowledgeConsole from './KnowledgeConsole';
import UserMenu from './UserMenu';
import TicketCategoryDonut from './charts/TicketCategoryDonut';
import SlaTrendChart from './charts/SlaTrendChart';
import TicketTrendChart from './charts/TicketTrendChart';
import AvgResolutionBar from './charts/AvgResolutionBar';
import TopServiceBarChart from './charts/TopServiceBarChart';
import UserManagementConsole from './admin/UserManagementConsole';
import SlaPolicyConsole from './admin/SlaPolicyConsole';
import NewTicketModal from './NewTicketModal';
import ServiceCatalogConsole from './admin/ServiceCatalogConsole';
import AuditTrailConsole from './admin/AuditTrailConsole';
import TicketManagementConsole from './admin/TicketManagementConsole';
import IntegrationConsole from './admin/IntegrationConsole';
import RequesterTopNav from './requester/RequesterTopNav';
import RequesterDashboard from './requester/RequesterDashboard';
import MyTicketsPage from './requester/MyTicketsPage';
import TicketDetail from './requester/TicketDetail';
import EvaCoverageDashboard from './eva/EvaCoverageDashboard';
import EvaArticleLibrary from './eva/EvaArticleLibrary';
import EvaFaqManager from './eva/EvaFaqManager';
import EvaDocuments from './eva/EvaDocuments';
import EvaPreview from './eva/EvaPreview';
import EvaUnansweredQuestions from './eva/EvaUnansweredQuestions';
import EvaConversationLog from './eva/EvaConversationLog';
import EvaRatingFeedback from './eva/EvaRatingFeedback';
import EvaAnalytics from './eva/EvaAnalytics';
import EvaAppsSystems from './eva/EvaAppsSystems';
import EvaSearchSettings from './eva/EvaSearchSettings';
import EvaTaxonomy from './eva/EvaTaxonomy';
import EvaTicketRecommendation from './eva/EvaTicketRecommendation';
import EvaTrainingOverview from './eva/EvaTrainingOverview';
import EvaAssistantWidget from './eva/EvaAssistantWidget';
import ApproverTopNav from './approver/ApproverTopNav';
import ApprovalInbox from './approver/ApprovalInbox';
import ApprovalTicketDetail from './approver/ApprovalTicketDetail';
import ApprovalHistoryPage from './approver/ApprovalHistoryPage';
import SupportDashboard from './support/SupportDashboard';
import SupportHistoryPage from './support/SupportHistoryPage';
import SupportTicketDetail from './support/SupportTicketDetail';
import SlaComplianceDonut from './charts/SlaComplianceDonut';
import TeamLeadTopNav from './teamlead/TeamLeadTopNav';
import TeamLeadWorkspace from './teamlead/TeamLeadWorkspace';
import TeamLeadTicketDetail from './teamlead/TeamLeadTicketDetail';

// Central map from `data-react="Name"` (set in Blade) to the component
// that should be mounted on that node. Add new islands here only.
export const registry = {
    RoleSwitcher,
    NotificationBell,
    NotificationHistoryPage,
    ThemeToggle,
    TicketWorkspace,
    AgentsPanel,
    SlaChart,
    CategoryChart,
    KnowledgeConsole,
    UserMenu,
    TicketCategoryDonut,
    SlaTrendChart,
    TicketTrendChart,
    AvgResolutionBar,
    TopServiceBarChart,
    UserManagementConsole,
    SlaPolicyConsole,
    NewTicketModal,
    ServiceCatalogConsole,
    AuditTrailConsole,
    TicketManagementConsole,
    IntegrationConsole,
    RequesterTopNav,
    RequesterDashboard,
    MyTicketsPage,
    TicketDetail,
    // EVA Knowledge Admin Console — komponen yang lupa didaftarkan di sini
    // gagal mount tanpa error, hanya console.warn.
    EvaCoverageDashboard,
    EvaArticleLibrary,
    EvaFaqManager,
    EvaDocuments,
    EvaPreview,
    EvaUnansweredQuestions,
    EvaConversationLog,
    EvaRatingFeedback,
    EvaAnalytics,
    EvaAppsSystems,
    EvaSearchSettings,
    EvaTaxonomy,
    EvaTicketRecommendation,
    EvaTrainingOverview,
    // Widget asisten di portal — satu-satunya komponen EVA yang hidup di luar
    // konsol admin.
    EvaAssistantWidget,
    ApproverTopNav,
    ApprovalInbox,
    ApprovalTicketDetail,
    ApprovalHistoryPage,
    SupportDashboard,
    SupportHistoryPage,
    SupportTicketDetail,
    SlaComplianceDonut,
    TeamLeadTopNav,
    TeamLeadWorkspace,
    TeamLeadTicketDetail,
};
