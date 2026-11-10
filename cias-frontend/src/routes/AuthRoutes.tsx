import { ForgotPasswordPage } from '../pages/auth/forgot-password'
import { LoginPage } from '../pages/auth/login'

export const authRoutes = [
  { path: '/login', element: <LoginPage /> },
  { path: '/forgot-password', element: <ForgotPasswordPage /> },
]
