import { Navigate, useLocation } from "react-router";
import { useAuth } from "../auth/auth-context";
import AuthLoadingScreen from "../components/AuthLoadingScreen";
import { hasPermission } from "../config/navigation";

export function RequireAuth({ children }) {
  const { user, loading } = useAuth();
  const location = useLocation();

  if (loading) return <AuthLoadingScreen />;

  if (!user) {
    return <Navigate to="/login" replace state={{ from: location.pathname }} />;
  }

  return children;
}

export function RequirePermission({ permission, children }) {
  const { user } = useAuth();

  if (!hasPermission(user, permission)) {
    return <Navigate to="/unauthorized" replace />;
  }

  return children;
}

export function PublicOnly({ children }) {
  const { user, loading } = useAuth();

  if (loading) return <AuthLoadingScreen />;
  if (user) return <Navigate to="/dashboard" replace />;

  return children;
}
