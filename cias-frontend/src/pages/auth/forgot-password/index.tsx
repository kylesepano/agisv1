import { Button } from '../../../components/ui/button'
import { Input } from '../../../components/ui/input'
import { PageHeader } from '../../../components/shared/PageHeader'
import { AuthLayout } from '../../../layouts/auth/AuthLayout'

export function ForgotPasswordPage() {
  return (
    <AuthLayout>
      <PageHeader title="Forgot Password" subtitle="Request a password reset link." />
      <form className="card form-stack">
        <Input placeholder="Email address" type="email" />
        <Button type="submit">Send Reset Link</Button>
      </form>
    </AuthLayout>
  )
}
