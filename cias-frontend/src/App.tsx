import { renderRoute, useCurrentPath } from "./routes";
import { ToastProvider } from "./components/ui/toast";
import { AuthProvider } from "./contexts/AuthContext";
import { useAuth } from "./hooks/useAuth";

function AppContent() {
  const path = useCurrentPath();
  const { isAuthenticated } = useAuth();

  return <ToastProvider>{renderRoute(path, isAuthenticated)}</ToastProvider>;
}

function App() {
  return (
    <AuthProvider>
      <AppContent />
    </AuthProvider>
  );
}

export default App;
