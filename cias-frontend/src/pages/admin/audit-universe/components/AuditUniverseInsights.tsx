import { CalendarDays } from 'lucide-react'
import {
  Cell,
  Pie,
  PieChart,
  ResponsiveContainer,
  Tooltip,
} from 'recharts'
import { Card, CardContent, CardHeader, CardTitle } from '../../../../components/ui/card'
import { Badge } from '../../../../components/ui/badge'
import { Heading, Text } from '../../../../components/ui/typography'
import type { AuditUniverseRecord } from '../audit-universe-types'
import { AuditUniverseRiskBadge } from './AuditUniverseUtils'

const categoryPalette: Record<AuditUniverseRecord['category'], string> = {
  Department: '#22c55e',
  Process: '#14b8a6',
  System: '#0ea5e9',
  Fund: '#f59e0b',
  Program: '#8b5cf6',
  Project: '#fb7185',
  Asset: '#94a3b8',
}

function percent(value: number, total: number) {
  if (total === 0) {
    return '0.0%'
  }

  return `${((value / total) * 100).toFixed(1)}%`
}

function DonutCenter({ value, label }: { value: string; label: string }) {
  return (
    <div className="pointer-events-none absolute inset-0 flex flex-col items-center justify-center">
      <Heading as={4} className="text-slate-950">
        {value}
      </Heading>
      <Text className="text-slate-500">{label}</Text>
    </div>
  )
}

export function AuditUniverseInsights({
  totalRecords,
  categoryCounts,
  upcomingAudits,
}: {
  totalRecords: number
  categoryCounts: Record<string, number>
  upcomingAudits: AuditUniverseRecord[]
}) {
  const categoryItems = (Object.entries(categoryCounts) as Array<
    [AuditUniverseRecord['category'], number]
  >)
    .map(([name, value]) => ({
      name,
      value,
      color: categoryPalette[name],
    }))
    .sort((left, right) => right.value - left.value)

  return (
    <div className="space-y-3">
      <Card className="rounded-2xl p-0">
        <CardHeader className="px-4 pt-4">
          <CardTitle className="text-sm">Audit Universe by Category</CardTitle>
        </CardHeader>
        <CardContent className="space-y-3 px-4 pb-4">
          <div className="mx-auto flex max-w-[220px] flex-col items-center">
            <div className="relative h-40 w-full">
              <ResponsiveContainer width="100%" height="100%">
                <PieChart>
                  <Pie
                    data={categoryItems}
                    dataKey="value"
                    innerRadius={36}
                    outerRadius={60}
                    paddingAngle={2}
                    strokeWidth={0}
                  >
                    {categoryItems.map((item) => (
                      <Cell key={item.name} fill={item.color} />
                    ))}
                  </Pie>
                  <Tooltip />
                </PieChart>
              </ResponsiveContainer>
              <DonutCenter value={String(totalRecords)} label="Total" />
            </div>
          </div>

          <div className="space-y-2">
            {categoryItems.map((item) => (
              <div key={item.name} className="flex items-center justify-between gap-4">
                <div className="flex items-center gap-3">
                  <span
                    className="h-3 w-3 rounded-full"
                    style={{ backgroundColor: item.color }}
                  />
                  <Text className="text-slate-700">{item.name}</Text>
                </div>
                <Text className="font-medium text-slate-700">
                  {item.value} ({percent(item.value, totalRecords)})
                </Text>
              </div>
            ))}
          </div>
        </CardContent>
      </Card>

      <Card className="rounded-2xl p-0">
        <CardHeader className="flex flex-row items-center justify-between gap-3 px-4 pt-4">
          <CardTitle className="text-sm">Upcoming Audits</CardTitle>
          <button
            className="font-semibold text-blue-600 transition hover:text-blue-700"
            type="button"
          >
            View All
          </button>
        </CardHeader>
        <CardContent className="space-y-3 px-4 pb-4">
          {upcomingAudits.length === 0 ? (
            <div className="rounded-xl border border-dashed border-slate-200 px-3 py-4 text-center">
              <Text className="text-slate-500">
                No upcoming audits found in the next 12 months.
              </Text>
            </div>
          ) : (
            upcomingAudits.slice(0, 5).map((audit) => (
              <div
                key={audit.code}
                className="rounded-xl border border-slate-200 bg-slate-50/80 p-3"
              >
                <div className="flex items-start justify-between gap-3">
                  <div className="min-w-0">
                    <Text className="truncate font-semibold text-slate-900">
                      {audit.name}
                    </Text>
                    <Text className="mt-1 text-slate-500">{audit.department}</Text>
                  </div>
                  <AuditUniverseRiskBadge riskLevel={audit.riskLevel} />
                </div>

                <div className="mt-2 flex items-center justify-between gap-3 text-slate-500">
                  <Text className="inline-flex items-center gap-1.5 text-slate-500">
                    <CalendarDays size={13} />
                    {audit.nextAudit}
                  </Text>
                  <Badge tone="neutral" className="bg-white text-slate-600">
                    {audit.code}
                  </Badge>
                </div>
              </div>
            ))
          )}

          <Text className="text-slate-500">
            Total upcoming audits: {upcomingAudits.length}
          </Text>
        </CardContent>
      </Card>
    </div>
  )
}
