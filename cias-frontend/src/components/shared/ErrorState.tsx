type ErrorStateProps = {
  message?: string
}

export function ErrorState({ message = 'Something went wrong.' }: ErrorStateProps) {
  return <div className="state-box error">{message}</div>
}
