import { Card } from "../../../../components/ui/card";
import { Text } from "../../../../components/ui/typography";
import {
  assessmentMatrixCriteria,
} from "../constants";
import type {
  AssessmentMatrixCriteriaKey,
  AssessmentMatrixScores,
} from "../risk-assessment-types";

export function RiskCriteriaScore({
  scores,
  remarks,
  onScoreChange,
  onRemarksChange,
}: {
  scores: AssessmentMatrixScores;
  remarks: string;
  onScoreChange: (key: AssessmentMatrixCriteriaKey, value: number) => void;
  onRemarksChange: (value: string) => void;
}) {
  return (
    <Card className="w-full rounded-2xl p-3.5">
      <div className="flex items-center justify-between gap-3 border-b border-slate-100 pb-2.5">
        <Text className="text-sm font-bold text-slate-900">
          2. Risk Criteria (Score 1 - 5)
        </Text>
        <Text className="text-xs text-slate-500">
          Score Guide: 1 = Very Low | 3 = Moderate | 5 = Very High
        </Text>
      </div>

      <div className="mt-2.5 overflow-x-auto">
        <table className="min-w-[660px] text-left text-sm">
          <thead className="border-b border-slate-100 text-[11px] uppercase tracking-[0.06em] text-slate-500">
            <tr>
              <th className="w-7 py-2 pr-2 font-semibold">#</th>
              <th className="w-[150px] py-2 pr-3 font-semibold">Risk Criterion</th>
              <th className="py-2 pr-2 font-semibold">Description</th>
              <th className="w-[176px] py-2 font-semibold text-right">Score (1-5)</th>
            </tr>
          </thead>
          <tbody>
            {assessmentMatrixCriteria.map((criterion, index) => (
              <tr key={criterion.key} className="border-b border-slate-100 align-top">
                <td className="py-2.5 pr-2 font-medium text-slate-700">{index + 1}</td>
                <td className="py-2.5 pr-3 font-semibold text-slate-800">
                  {criterion.label}
                </td>
                <td className="py-2.5 pr-2 text-slate-600">{criterion.description}</td>
                <td className="py-2.5">
                  <div className="flex justify-end gap-1.5">
                    {[1, 2, 3, 4, 5].map((value) => (
                      <button
                        key={value}
                        className={[
                          "grid h-8 w-8 place-items-center rounded-full border text-xs font-semibold transition",
                          scores[criterion.key] === value
                            ? "border-blue-600 bg-blue-600 text-white"
                            : "border-slate-200 bg-white text-slate-600 hover:border-blue-300 hover:text-blue-600",
                        ].join(" ")}
                        type="button"
                        onClick={() => onScoreChange(criterion.key, value)}
                      >
                        {value}
                      </button>
                    ))}
                  </div>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      <div className="mt-3.5 space-y-1.5">
        <Text className="text-xs font-semibold text-slate-600">Remarks</Text>
        <textarea
          className="min-h-[96px] w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm transition-colors placeholder:text-slate-400 focus:border-blue-300 focus:outline-none focus:ring-2 focus:ring-blue-100"
          value={remarks}
          onChange={(event) => onRemarksChange(event.target.value)}
        />
      </div>
    </Card>
  );
}
