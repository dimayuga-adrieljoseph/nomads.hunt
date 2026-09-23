/** Format a number as Philippine Peso */
export function formatPeso(amount: number): string {
  return '₱' + amount.toLocaleString('en-PH')
}

/** Human-readable product status label */
export function productStatusLabel(status: string): string {
  const labels: Record<string, string> = {
    available:     'Available',
    mine_pending:  'Mine Active',
    steal_pending: 'Steal Active',
    grab_pending:  'Grab Payment',
    sold:          'Sold',
  }
  return labels[status] ?? status
}

/** CSS class for product status badge */
export function productStatusClass(status: string): string {
  const classes: Record<string, string> = {
    available:     'badge--available',
    mine_pending:  'badge--mine',
    steal_pending: 'badge--steal',
    grab_pending:  'badge--grab',
    sold:          'badge--sold',
  }
  return classes[status] ?? 'badge--default'
}

/** Human-readable claim status label */
export function claimStatusLabel(status: string): string {
  const labels: Record<string, string> = {
    waiting:    'Waiting',
    active:     'Active',
    expired:    'Expired',
    completed:  'Completed',
    overridden: 'Overridden',
    cancelled:  'Cancelled',
  }
  return labels[status] ?? status
}

/** Human-readable claim type label */
export function claimTypeLabel(type: string): string {
  return type.toUpperCase()
}

/** Human-readable payment status */
export function paymentStatusLabel(status: string): string {
  const labels: Record<string, string> = {
    pending:   'Payment Pending',
    paid:      'Paid',
    expired:   'Payment Expired',
    cancelled: 'Cancelled',
  }
  return labels[status] ?? status
}

/** Format ISO date string to readable local date */
export function formatDate(iso: string | null | undefined): string {
  if (!iso) return '—'
  return new Date(iso).toLocaleDateString('en-PH', {
    year: 'numeric', month: 'short', day: 'numeric',
    hour: '2-digit', minute: '2-digit',
  })
}

/** Condition display */
export function conditionLabel(condition: string): string {
  const labels: Record<string, string> = {
    excellent: 'Excellent',
    good:      'Good',
    fair:      'Fair',
    poor:      'Poor',
  }
  return labels[condition] ?? condition
}
