import RoleSwitcher from './RoleSwitcher';
import NotificationBell from './NotificationBell';
import TicketWorkspace from './TicketWorkspace';
import ApprovalQueue from './ApprovalQueue';
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
import RequesterTopNav from './requester/RequesterTopNav';
import RequesterDashboard from './requester/RequesterDashboard';
import MyTicketsPage from './requester/MyTicketsPage';
import TicketDetail from './requester/TicketDetail';

// Central map from `data-react="Name"` (set in Blade) to the component
// that should be mounted on that node. Add new islands here only.
export const registry = {
    RoleSwitcher,
    NotificationBell,
    TicketWorkspace,
    ApprovalQueue,
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
    RequesterTopNav,
    RequesterDashboard,
    MyTicketsPage,
    TicketDetail,
};
