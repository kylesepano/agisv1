import { useMemo, useRef, useState, type CSSProperties } from "react";
import FullCalendar from "@fullcalendar/react";
import dayGridPlugin from "@fullcalendar/daygrid";
import timeGridPlugin from "@fullcalendar/timegrid";
import listPlugin from "@fullcalendar/list";
import interactionPlugin from "@fullcalendar/interaction";
import type { DateSelectArg, EventClickArg } from "@fullcalendar/core";
import {
  CalendarDays,
  ChevronLeft,
  ChevronRight,
  CircleAlert,
  List,
} from "lucide-react";
import { Button } from "../../../../components/ui/button";
import { Card } from "../../../../components/ui/card";
import { Text } from "../../../../components/ui/typography";
import type { AuditCalendarItem } from "../annual-audit-planning-types";
import {
  formatAuditCalendarPanelDate,
  formatAuditScheduleDate,
  getPlanStatusColor,
} from "../annual-audit-planning-utils";
import { PlanStatusBadge } from "./PlanStatusBadge";
import "./audit-calendar.css";

type CalendarViewMode = "dayGridMonth" | "timeGridWeek" | "listMonth";

function toDateOnly(value: Date) {
  return value.toISOString().slice(0, 10);
}

function normalizeCalendarDate(value: string) {
  return value.includes("T") ? value.slice(0, 10) : value;
}

function CalendarDetailCard({ item }: { item: AuditCalendarItem }) {
  return (
    <div
      className="rounded-2xl border border-slate-200 bg-white p-3.5 shadow-sm"
      style={{ borderLeftWidth: 3, borderLeftColor: getPlanStatusColor(item.status) }}
    >
      <div className="flex items-start justify-between gap-3">
        <Text className="text-sm font-semibold text-slate-900">{item.title}</Text>
        <PlanStatusBadge status={item.status} />
      </div>

      <div className="mt-3 grid gap-1.5 text-[13px] text-slate-600">
        <div className="grid grid-cols-[92px_1fr] gap-2">
          <Text className="font-medium text-slate-500">Office / Unit</Text>
          <Text>{item.office}</Text>
        </div>
        <div className="grid grid-cols-[92px_1fr] gap-2">
          <Text className="font-medium text-slate-500">Category</Text>
          <Text>{item.category}</Text>
        </div>
        <div className="grid grid-cols-[92px_1fr] gap-2">
          <Text className="font-medium text-slate-500">Plan Start</Text>
          <Text>{formatAuditScheduleDate(item.planStart)}</Text>
        </div>
        <div className="grid grid-cols-[92px_1fr] gap-2">
          <Text className="font-medium text-slate-500">Plan End</Text>
          <Text>{formatAuditScheduleDate(item.planEnd)}</Text>
        </div>
        <div className="grid grid-cols-[92px_1fr] gap-2">
          <Text className="font-medium text-slate-500">Audit Team</Text>
          <Text>{item.auditTeam}</Text>
        </div>
      </div>

      <button
        className="mt-3 text-sm font-semibold text-blue-600 transition hover:text-blue-700"
        type="button"
      >
        View Details
      </button>
    </div>
  );
}

export function AuditCalendarView({
  items,
  initialDate = "2025-06-01",
}: {
  items: AuditCalendarItem[];
  initialDate?: string;
}) {
  const calendarRef = useRef<FullCalendar | null>(null);
  const [currentView, setCurrentView] = useState<CalendarViewMode>("dayGridMonth");
  const [calendarTitle, setCalendarTitle] = useState("June 2025");
  const [selectedDate, setSelectedDate] = useState("2025-06-26");

  const events = useMemo(
    () =>
      items.map((item) => ({
        id: item.id,
        title: item.title,
        start: item.scheduleDate,
        allDay: true,
        backgroundColor: "transparent",
        borderColor: "transparent",
        textColor: getPlanStatusColor(item.status),
        classNames: ["audit-calendar-event"],
        extendedProps: item,
      })),
    [items],
  );

  const selectedItems = useMemo(
    () => items.filter((item) => item.scheduleDate === selectedDate),
    [items, selectedDate],
  );

  const syncCalendarState = () => {
    const api = calendarRef.current?.getApi();
    if (!api) {
      return;
    }

    setCalendarTitle(api.view.title);
    setCurrentView(api.view.type as CalendarViewMode);
  };

  const handlePrev = () => {
    calendarRef.current?.getApi().prev();
    syncCalendarState();
  };

  const handleNext = () => {
    calendarRef.current?.getApi().next();
    syncCalendarState();
  };

  const handleToday = () => {
    calendarRef.current?.getApi().today();
    syncCalendarState();
    setSelectedDate(toDateOnly(new Date()));
  };

  const handleViewChange = (view: CalendarViewMode) => {
    calendarRef.current?.getApi().changeView(view);
    syncCalendarState();
    setCurrentView(view);
  };

  const handleDateSelect = (arg: DateSelectArg) => {
    setSelectedDate(normalizeCalendarDate(arg.startStr));
  };

  const handleEventClick = (arg: EventClickArg) => {
    if (arg.event.startStr) {
      setSelectedDate(normalizeCalendarDate(arg.event.startStr));
    }
  };

  return (
    <Card className="rounded-2xl p-3">
      <div className="grid gap-3 xl:grid-cols-[minmax(0,1.68fr)_300px]">
        <div className="min-w-0">
          <div className="mb-3 flex items-center justify-between gap-3 max-[860px]:flex-col max-[860px]:items-start">
            <div className="flex items-center gap-2">
              <button
                className="grid h-8 w-8 place-items-center rounded-lg border border-slate-200 bg-white text-slate-600 transition hover:bg-slate-50"
                type="button"
                onClick={handlePrev}
              >
                <ChevronLeft className="h-4 w-4" />
              </button>
              <button
                className="grid h-8 w-8 place-items-center rounded-lg border border-slate-200 bg-white text-slate-600 transition hover:bg-slate-50"
                type="button"
                onClick={handleNext}
              >
                <ChevronRight className="h-4 w-4" />
              </button>
              <Button className="h-8 rounded-lg px-3 text-[11px]" variant="outline" onClick={handleToday}>
                Today
              </Button>
              <Text className="ml-2 text-[1.85rem] font-bold text-slate-950">
                {calendarTitle}
              </Text>
            </div>

            <div className="flex items-center rounded-xl border border-slate-200 p-1">
              {[
                { label: "Month", value: "dayGridMonth" },
                { label: "Week", value: "timeGridWeek" },
                { label: "List", value: "listMonth" },
              ].map((view) => (
                <button
                  key={view.value}
                  className={
                    currentView === view.value
                      ? "rounded-lg bg-blue-600 px-3 py-1.5 text-[11px] font-semibold text-white"
                      : "rounded-lg px-3 py-1.5 text-[11px] font-semibold text-slate-600 transition hover:bg-slate-100"
                  }
                  type="button"
                  onClick={() => handleViewChange(view.value as CalendarViewMode)}
                >
                  {view.label}
                </button>
              ))}
            </div>
          </div>

          <div className="audit-calendar-shell overflow-hidden rounded-2xl border border-slate-200 bg-white">
            <FullCalendar
              ref={calendarRef}
              plugins={[dayGridPlugin, timeGridPlugin, listPlugin, interactionPlugin]}
              initialView="dayGridMonth"
              initialDate={initialDate}
              headerToolbar={false}
              events={events}
              eventDisplay="block"
              height="auto"
              fixedWeekCount={false}
              selectable
              dayMaxEvents={3}
              select={handleDateSelect}
              eventClick={handleEventClick}
              datesSet={syncCalendarState}
              dayCellClassNames={() => "bg-white"}
              eventContent={(eventInfo) => (
                <div
                  className="audit-calendar-chip"
                  style={
                    {
                      "--chip-color": getPlanStatusColor(
                        eventInfo.event.extendedProps.status,
                      ),
                    } as CSSProperties
                  }
                >
                  <span className="audit-calendar-chip-dot" />
                  <span className="truncate">{eventInfo.event.title}</span>
                </div>
              )}
            />
          </div>
        </div>

        <div className="space-y-3">
          <div className="rounded-2xl border border-slate-200 bg-white p-3.5">
            <div className="flex items-start justify-between gap-3">
              <div>
                <Text className="text-base font-bold text-slate-950">
                  {formatAuditCalendarPanelDate(selectedDate)}
                </Text>
                <div className="mt-1 inline-flex rounded-full bg-blue-50 px-2 py-0.5 text-[11px] font-semibold text-blue-600">
                  {selectedItems.length} Planned Audit(s)
                </div>
              </div>
              <CalendarDays className="h-5 w-5 text-blue-600" />
            </div>
          </div>

          <div className="space-y-2.5">
            {selectedItems.length === 0 ? (
              <div className="rounded-2xl border border-dashed border-slate-200 bg-slate-50 p-4 text-sm text-slate-500">
                No planned audits active for the selected date.
              </div>
            ) : (
              selectedItems.map((item) => (
                <CalendarDetailCard key={item.id} item={item} />
              ))
            )}
          </div>

          <Button className="h-9 w-full rounded-xl text-sm" variant="outline">
            <List className="h-4 w-4" />
            View All Planned Audits
          </Button>
        </div>
      </div>

      <div className="mt-3 flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 pt-3">
        <div className="flex flex-wrap items-center gap-4 text-xs text-slate-600">
          {[
            { label: "Completed", color: "#22c55e" },
            { label: "In Progress", color: "#2563eb" },
            { label: "Planned", color: "#8b5cf6" },
            { label: "Cancelled", color: "#ef4444" },
          ].map((legend) => (
            <div key={legend.label} className="flex items-center gap-2">
              <span
                className="h-2.5 w-2.5 rounded-full"
                style={{ backgroundColor: legend.color }}
              />
              <span>{legend.label}</span>
            </div>
          ))}
        </div>

        <div className="flex items-center gap-2 text-xs text-slate-500">
          <CircleAlert className="h-3.5 w-3.5" />
          <span>Click on an event to view more details.</span>
        </div>
      </div>
    </Card>
  );
}
