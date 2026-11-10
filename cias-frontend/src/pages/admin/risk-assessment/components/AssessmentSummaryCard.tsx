import { Button } from "../../../../components/ui/button";
import { Card } from "../../../../components/ui/card";
import { Text } from "../../../../components/ui/typography";
import { BookOpen, CircleAlert } from "lucide-react";
import { assessmentMatrixScoreGuide } from "../constants";
import type { RiskAssessmentRating } from "../risk-assessment-types";

function ratingTone(rating: RiskAssessmentRating) {
  if (rating === "Critical") {
    return "bg-rose-600 text-white";
  }
  if (rating === "High") {
    return "bg-red-500 text-white";
  }
  if (rating === "Medium") {
    return "bg-amber-400 text-amber-950";
  }
  return "bg-emerald-500 text-white";
}

function priorityTone(priority: string) {
  if (priority === "High Priority") {
    return "bg-rose-500";
  }
  if (priority === "Moderate Priority") {
    return "bg-amber-500";
  }
  return "bg-emerald-500";
}

function scoreGuideDotTone(label: string) {
  if (label === "Critical Risk") {
    return "bg-rose-500";
  }
  if (label === "High Risk") {
    return "bg-orange-500";
  }
  if (label === "Medium Risk") {
    return "bg-amber-500";
  }
  return "bg-emerald-500";
}

export function AssessmentSummaryCard({
  totalScore,
  rating,
  priority,
  onReset,
  onSave,
}: {
  totalScore: number;
  rating: RiskAssessmentRating;
  priority: string;
  onReset: () => void;
  onSave: () => void;
}) {
  return (
    <Card className="flex min-h-[500px] w-full min-w-[320px] self-start flex-col rounded-[20px] border border-slate-200 bg-white px-4 py-4 shadow-sm">
      <Text className="text-sm font-bold text-slate-900">
        3. Assessment Summary
      </Text>

      <div className="mt-4 flex flex-1 flex-col gap-4">
        <div className="grid overflow-hidden rounded-[16px] border border-slate-200 bg-white sm:grid-cols-2">
          <div className="flex min-h-[108px] flex-col items-center justify-center px-4 py-4 text-center">
            <Text className="text-sm font-semibold text-slate-500">
              Total Score
            </Text>
            <div className="mt-1.5 flex items-end gap-1">
              <Text className="text-[2.4rem] leading-none font-bold tracking-[-0.03em] text-blue-700">
                {totalScore}
              </Text>
              <Text className="pb-0.5 text-base leading-none font-semibold text-slate-500">
                / 35
              </Text>
            </div>
          </div>

          <div className="border-t border-slate-200 sm:border-t-0 sm:border-l">
            <div className="flex min-h-[108px] flex-col items-center justify-center px-4 py-4 text-center">
              <Text className="text-sm font-semibold text-slate-500">
                Risk Rating
              </Text>
              <div
                className={[
                  "mt-3 inline-flex min-w-[92px] justify-center rounded-lg px-5 py-2 text-sm leading-none font-bold uppercase shadow-sm",
                  ratingTone(rating),
                ].join(" ")}
              >
                {rating}
              </div>
            </div>
          </div>
        </div>

        <div className="rounded-[14px] border border-rose-200 bg-gradient-to-r from-rose-50 via-white to-rose-50 px-4 py-3.5">
          <div className="flex items-center gap-3">
            <span
              className={[
                "inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-white shadow-sm",
                priorityTone(priority),
              ].join(" ")}
            >
              <CircleAlert className="h-4 w-4" />
            </span>
            <div>
              <Text className="text-xs font-medium text-slate-500">
                Priority for Audit
              </Text>
              <Text className="mt-0.5 text-lg leading-none font-bold text-slate-900">
                {priority}
              </Text>
            </div>
          </div>
        </div>

        <div>
          <div className="mb-2.5 flex items-center gap-2.5 px-1">
            <BookOpen className="h-4.5 w-4.5 text-blue-700" />
            <Text className="text-sm font-bold text-slate-900">
              Score Guide
            </Text>
          </div>

          <div className="overflow-hidden rounded-[14px] border border-slate-200 bg-white shadow-sm">
            {assessmentMatrixScoreGuide.map((item, index) => (
              <div
                key={item.range}
                className={[
                  "grid min-h-[46px] grid-cols-[88px_1fr] items-center",
                  index !== 0 ? "border-t border-slate-200" : "",
                ].join(" ")}
              >
                <div className="flex h-full items-center border-r border-slate-200 px-4">
                  <Text className="text-sm font-medium text-slate-800">
                    {item.range}
                  </Text>
                </div>
                <div className="flex items-center gap-3 px-4">
                  <span
                    className={`h-2.5 w-2.5 rounded-full ${scoreGuideDotTone(item.label)}`}
                  />
                  <Text className={`text-sm font-medium ${item.tone}`}>
                    {item.label}
                  </Text>
                </div>
              </div>
            ))}
          </div>
        </div>
      </div>

      <div className="mt-auto grid grid-cols-2 gap-3 pt-6">
        <Button
          className="h-9 rounded-lg px-3 text-xs"
          type="button"
          variant="outline"
          onClick={onReset}
        >
          Reset
        </Button>
        <Button
          className="h-9 rounded-lg px-3 text-xs"
          type="button"
          onClick={onSave}
        >
          Save Assessment
        </Button>
      </div>
    </Card>
  );
}
